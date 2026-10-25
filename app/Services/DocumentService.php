<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\ApplicationDocument;
use App\Models\GrantApplication;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/**
 * Unggah dokumen yang aman: validasi tipe/ukuran + magic bytes, pemindaian opsional,
 * penyimpanan privat, hash SHA-256, dan versi baru (versi lama tidak pernah dihapus).
 */
class DocumentService
{
    private const SIGNATURES = [
        'pdf' => ["\x25\x50\x44\x46"],
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47"],
    ];

    public function __construct(private AuditService $audit)
    {
    }

    /** Siapa boleh mengunggah dokumen tahap tertentu pada status tertentu. */
    public function canUpload(User $user, GrantApplication $app, string $stage): bool
    {
        $mine = $user->organization_id && $user->organization_id === $app->applicant_org_id;
        $myOpd = $user->organization_id && $user->organization_id === $app->opd_id;
        $s = $app->status;

        return match ($stage) {
            'PENGAJUAN' => $user->hasRole('PEMOHON') && $mine && $s->isEditableByApplicant(),
            'NPHD' => $s === Status::BUDGETED
                && (($user->hasRole('OPD') && $myOpd) || ($user->hasRole('PEMOHON') && $mine)),
            'PENCAIRAN', 'PENYERAHAN' => in_array($s, [Status::NPHD_SIGNED, Status::DISBURSED], true)
                && (($user->hasRole('OPD') && $myOpd) || ($user->hasRole('PEMOHON') && $mine) || $user->hasRole('BPKAD')),
            'LPJ' => $user->hasRole('PEMOHON') && $mine && $s === Status::DISBURSED,
            'ARSIP' => $user->hasRole('OPD') && $myOpd && $s === Status::LPJ_REVIEW,
            default => false,
        };
    }

    /**
     * @param  array{0:string,1:int}|null  $related
     */
    public function store(
        GrantApplication $app,
        User $user,
        string $stage,
        string $docType,
        UploadedFile $file,
        ?array $related = null,
        ?string $validUntil = null,
    ): ApplicationDocument {
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = Setting::get('upload.mimes');
        $maxBytes = Setting::int('upload.max_mb') * 1024 * 1024;

        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'Tipe berkas tidak diizinkan. Gunakan: '.implode(', ', $allowed).'.']);
        }
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages(['file' => 'Ukuran berkas melebihi '.Setting::int('upload.max_mb').' MB.']);
        }
        $this->assertMagicBytes($file, $ext);
        $this->scan($file);

        $requirement = (new DocumentChecklist)->requirementsFor($app, $stage)->firstWhere('code', $docType);
        if (! $requirement) {
            throw ValidationException::withMessages(['doc_type' => 'Jenis dokumen tidak dikenal untuk tahap ini.']);
        }
        if ($requirement->requires_validity && ! $validUntil) {
            throw ValidationException::withMessages(['valid_until' => 'Tanggal masa berlaku dokumen wajib diisi.']);
        }

        return DB::transaction(function () use ($app, $user, $stage, $docType, $file, $related, $validUntil, $requirement, $ext) {
            $scope = fn ($q) => $q->where('application_id', $app->id)->where('doc_type', $docType)
                ->when($related, fn ($q) => $q->where('related_type', $related[0])->where('related_id', $related[1]),
                    fn ($q) => $q->whereNull('related_type'));

            $previous = ApplicationDocument::query()->where($scope)->orderByDesc('version')->lockForUpdate()->first();
            $version = ($previous?->version ?? 0) + 1;

            $path = sprintf('applications/%d/%s/v%d_%s.%s', $app->id, Str::lower($docType), $version, Str::random(12), $ext);
            Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

            if ($previous) {
                ApplicationDocument::query()->where($scope)->where('is_current', true)
                    ->update(['is_current' => false, 'replaced_at' => now()]);
            }

            $doc = ApplicationDocument::create([
                'application_id' => $app->id,
                'requirement_id' => $requirement->id,
                'doc_type' => $docType,
                'stage' => $stage,
                'related_type' => $related[0] ?? null,
                'related_id' => $related[1] ?? null,
                'version' => $version,
                'is_current' => true,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'file_path' => $path,
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'valid_until' => $validUntil,
                'uploaded_by' => $user->id,
            ]);

            $this->audit->record($version > 1 ? 'DOCUMENT_REPLACED' : 'DOCUMENT_UPLOADED', $doc, null, [
                'doc_type' => $docType, 'version' => $version, 'hash' => $doc->file_hash, 'name' => $doc->original_name,
            ], 'PROSES', $app->id, $user);

            return $doc;
        });
    }

    public function review(ApplicationDocument $doc, User $reviewer, string $result, ?string $note): ApplicationDocument
    {
        if (! $doc->is_current) {
            throw ValidationException::withMessages(['workflow' => 'Hanya versi dokumen terbaru yang dapat diperiksa.']);
        }
        if ($result === 'TIDAK_SESUAI' && blank($note)) {
            throw ValidationException::withMessages(['review_note' => 'Catatan wajib diisi bila dokumen tidak sesuai.']);
        }
        $old = ['review_status' => $doc->review_status];
        $doc->update([
            'review_status' => $result, 'review_note' => $note,
            'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
        ]);
        $this->audit->record('DOCUMENT_REVIEWED', $doc, $old, ['review_status' => $result, 'note' => $note], 'PROSES', $doc->application_id, $reviewer);

        return $doc;
    }

    private function assertMagicBytes(UploadedFile $file, string $ext): void
    {
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 8);
        foreach (self::SIGNATURES[$ext] ?? [] as $sig) {
            if (str_starts_with($head, $sig)) {
                return;
            }
        }
        throw ValidationException::withMessages(['file' => 'Isi berkas tidak sesuai dengan ekstensinya (kemungkinan berkas rusak atau dipalsukan).']);
    }

    private function scan(UploadedFile $file): void
    {
        if (config('ehibah.antivirus.driver') !== 'clamscan') {
            return;
        }
        $p = new Process([config('ehibah.antivirus.binary'), '--no-summary', $file->getRealPath()]);
        $p->setTimeout(60)->run();
        if ($p->getExitCode() === 1) {
            throw ValidationException::withMessages(['file' => 'Berkas ditolak: terdeteksi malware.']);
        }
        if ($p->getExitCode() > 1) {
            // gagal memindai = gagal tertutup (fail-closed)
            throw ValidationException::withMessages(['file' => 'Pemindaian keamanan berkas gagal. Coba lagi atau hubungi admin.']);
        }
    }
}
