<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\ApplicationDocument;
use App\Models\GrantApplication;
use App\Services\AuditService;
use App\Services\DocumentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $docs, private AuditService $audit)
    {
    }

    public function store(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $d = $request->validate([
            'stage' => 'required|string', 'doc_type' => 'required|string|max:40', 'file' => 'required|file',
            'valid_until' => 'nullable|date|after:today', 'related_id' => 'nullable|integer',
        ], ['file.required' => 'Pilih berkas yang akan diunggah.', 'valid_until.after' => 'Masa berlaku dokumen sudah lewat.']);

        if (! $this->docs->canUpload($request->user(), $application, $d['stage'])) {
            throw new AuthorizationException('Anda tidak dapat mengunggah dokumen tahap ini pada status "'.$application->status->label().'".');
        }
        $related = ! empty($d['related_id']) ? ['report', (int) $d['related_id']] : null;
        $doc = $this->docs->store($application, $request->user(), $d['stage'], $d['doc_type'], $request->file('file'), $related, $d['valid_until'] ?? null);

        return back()->with('success', "Dokumen terunggah (versi {$doc->version}). Versi sebelumnya tetap tersimpan sebagai riwayat.");
    }

    /** Unduhan melalui controller (berkas privat) — terotorisasi & tercatat. */
    public function download(Request $request, ApplicationDocument $document)
    {
        $app = $document->application_id ? GrantApplication::findOrFail($document->application_id) : abort(404);
        $this->authorizeView($app);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'Berkas tidak ditemukan di penyimpanan.');

        $this->audit->access('DOCUMENT_DOWNLOADED', $request->user(), ['document_id' => $document->id, 'application_id' => $app->id, 'doc_type' => $document->doc_type]);

        return Storage::disk('local')->download($document->file_path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, ApplicationDocument $document)
    {
        $app = GrantApplication::findOrFail($document->application_id);
        $this->authorizeView($app);
        $u = $request->user();

        $opd = $u->hasRole('OPD') && $u->organization_id === $app->opd_id
            && in_array($app->status, [Status::ADMIN_CHECK, Status::BUDGETED, Status::NPHD_SIGNED, Status::DISBURSED, Status::LPJ_REVIEW], true);
        $bpkad = $u->hasRole('BPKAD') && in_array($document->stage, ['PENCAIRAN', 'PENYERAHAN'], true);
        if (! $opd && ! $bpkad) {
            throw new AuthorizationException('Anda tidak berwenang memeriksa dokumen ini pada status saat ini.');
        }
        $request->validate(['result' => 'required|in:SESUAI,TIDAK_SESUAI', 'review_note' => 'nullable|string|max:1000']);
        $this->docs->review($document, $u, $request->input('result'), $request->input('review_note'));

        return back()->with('success', 'Hasil pemeriksaan dokumen tersimpan.');
    }
}
