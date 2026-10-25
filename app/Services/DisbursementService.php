<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Disbursement;
use App\Models\GrantApplication;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Penyaluran hibah UANG (Perbup Pasal 9): SPP -> SPM -> SP2D-LS -> transfer RKUD.
 * Hibah barang tidak pernah melewati jalur ini.
 */
class DisbursementService
{
    public function __construct(
        private AuditService $audit,
        private GrantCalculator $calc,
        private DocumentChecklist $docs,
        private WorkflowService $workflow,
    ) {
    }

    /** Rencana tahap beserta status masing-masing (untuk UI). */
    public function plan(GrantApplication $app): array
    {
        $plan = $this->calc->stagePlan((int) $app->amount_approved, $app->disbursement_scheme);
        $existing = $app->disbursements()->whereIn('status', ['DIAJUKAN', 'DISALURKAN'])->get()->keyBy('stage');
        foreach ($plan as $stage => &$p) {
            $p['disbursement'] = $existing->get($stage);
            $p['blockers'] = $this->stageBlockers($app, $stage);
        }

        return $plan;
    }

    public function stageBlockers(GrantApplication $app, int $stage): array
    {
        $e = [];
        if (! $app->isMoney()) {
            return ['Hibah barang tidak melalui pencairan uang; gunakan jalur penyerahan barang (BAST).'];
        }
        if (! in_array($app->status, [Status::NPHD_SIGNED, Status::DISBURSED], true)) {
            $e[] = 'NPHD belum ditandatangani — penyaluran tidak dapat diajukan.';

            return $e;
        }
        $nphd = $app->activeNphd();
        if (! $nphd || $nphd->status !== 'DITANDATANGANI') {
            $e[] = 'NPHD belum lengkap/ditandatangani.';
        }
        $plan = $this->calc->stagePlan((int) $app->amount_approved, $app->disbursement_scheme);
        if (! isset($plan[$stage])) {
            return ['Tahap tidak tersedia untuk skema penyaluran ini.'];
        }
        if ($stage === 2) {
            $s1 = $app->disbursements()->where('stage', 1)->where('status', 'DISALURKAN')->exists();
            if (! $s1) {
                $e[] = 'Tahap I belum disalurkan.';
            }
            $interim = $app->reports()->where('report_type', 'TAHAP_1')->where('review_status', 'DITERIMA')->exists();
            if (! $interim) {
                $e[] = 'Laporan penggunaan tahap I belum diterima OPD — tahap II terkunci (Pasal 9).';
            }
        }
        foreach ($this->docs->missing($app, 'PENCAIRAN') as $m) {
            $e[] = "Dokumen pencairan belum lengkap: $m.";
        }

        return $e;
    }

    /** OPD mengajukan penyaluran (SPP). Idempoten terhadap kunci yang sama. */
    public function request(GrantApplication $app, User $user, int $stage, string $idempotencyKey, ?string $sppNo, ?string $sppDate, ?string $notes = null): Disbursement
    {
        if (! $user->hasRole('OPD') || $user->organization_id !== $app->opd_id) {
            throw new AuthorizationException('Hanya OPD pemilik urusan yang mengajukan penyaluran.');
        }

        return DB::transaction(function () use ($app, $user, $stage, $idempotencyKey, $sppNo, $sppDate, $notes) {
            $app = GrantApplication::query()->lockForUpdate()->with(['grantType', 'nphds'])->findOrFail($app->id);

            // permintaan ulang dengan kunci yang sama (klik ganda/retry) => hasil yang sama, bukan transaksi baru
            if ($dupe = Disbursement::query()->where('idempotency_key', $idempotencyKey)->where('application_id', $app->id)->first()) {
                return $dupe;
            }
            if ($errors = $this->stageBlockers($app, $stage)) {
                throw ValidationException::withMessages(['workflow' => $errors]);
            }
            if ($app->disbursements()->where('stage', $stage)->whereIn('status', ['DIAJUKAN', 'DISALURKAN'])->exists()) {
                throw ValidationException::withMessages(['workflow' => "Tahap $stage sudah diajukan/disalurkan. Pembayaran ganda ditolak."]);
            }

            $item = $this->calc->stagePlan((int) $app->amount_approved, $app->disbursement_scheme)[$stage];
            $committed = (int) $app->disbursements()->whereIn('status', ['DIAJUKAN', 'DISALURKAN'])->sum('amount');
            $nphd = $app->activeNphd();
            if ($committed + $item['amount'] > $nphd->amount) {
                throw ValidationException::withMessages(['workflow' => 'Penyaluran melebihi nilai NPHD ('.Fmt::rupiah($nphd->amount).').']);
            }

            $d = Disbursement::create([
                'application_id' => $app->id, 'stage' => $stage, 'percentage' => $item['percentage'], 'amount' => $item['amount'],
                'status' => 'DIAJUKAN', 'idempotency_key' => $idempotencyKey, 'spp_no' => $sppNo, 'spp_date' => $sppDate,
                'notes' => $notes, 'requested_by' => $user->id,
            ]);
            $this->audit->record('DISBURSEMENT_REQUESTED', $d, null, ['stage' => $stage, 'amount' => $d->amount], 'PROSES', $app->id, $user);

            return $d;
        });
    }

    /** BPKAD memproses SPM/SP2D-LS dan mencatat transfer dari RKUD. */
    public function process(Disbursement $disb, User $user, array $data): Disbursement
    {
        if (! $user->hasRole('BPKAD')) {
            throw new AuthorizationException('Hanya BPKAD/BUD yang memproses SP2D.');
        }
        $x = Validator::make($data, [
            'spm_no' => 'required|string|max:60', 'sp2d_no' => 'required|string|max:60',
            'sp2d_date' => 'required|date|before_or_equal:today', 'rkud_ref' => 'required|string|max:80',
            'bank_account_ref' => 'required|string|max:80',
        ], [], [
            'spm_no' => 'Nomor SPM', 'sp2d_no' => 'Nomor SP2D-LS', 'sp2d_date' => 'Tanggal SP2D',
            'rkud_ref' => 'Bukti transfer RKUD', 'bank_account_ref' => 'Rekening tujuan',
        ])->validate();

        $app = DB::transaction(function () use ($disb, $user, $x) {
            $disb = Disbursement::query()->lockForUpdate()->findOrFail($disb->id);
            if ($disb->status !== 'DIAJUKAN') {
                throw ValidationException::withMessages(['workflow' => 'Penyaluran ini sudah diproses.']);
            }
            $app = GrantApplication::query()->lockForUpdate()->with('nphds')->findOrFail($disb->application_id);
            $paid = (int) $app->disbursements()->where('status', 'DISALURKAN')->sum('amount');
            if ($paid + $disb->amount > $app->activeNphd()->amount) {
                throw ValidationException::withMessages(['workflow' => 'Penyaluran melebihi nilai NPHD.']);
            }
            $disb->update($x + ['status' => 'DISALURKAN', 'paid_at' => now(), 'processed_by' => $user->id]);
            $this->audit->record('DISBURSEMENT_PAID', $disb, ['status' => 'DIAJUKAN'], [
                'status' => 'DISALURKAN', 'sp2d_no' => $x['sp2d_no'], 'amount' => $disb->amount,
            ], 'PROSES', $app->id, $user);

            return $app;
        });

        if ($app->status === Status::NPHD_SIGNED) {
            $this->workflow->system($app, 'mark_disbursed', $user);
        }
        // penyaluran terakhir ditandai: tenggat LPJ dihitung ulang dari rencana kegiatan bila belum ada
        $disb->refresh();

        return $disb;
    }

    public function reject(Disbursement $disb, User $user, string $reason): Disbursement
    {
        if (! $user->hasRole('BPKAD')) {
            throw new AuthorizationException('Hanya BPKAD yang dapat menolak pencairan.');
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi.']);
        }
        if ($disb->status !== 'DIAJUKAN') {
            throw ValidationException::withMessages(['workflow' => 'Hanya pengajuan berstatus DIAJUKAN yang dapat ditolak.']);
        }
        $disb->update(['status' => 'DITOLAK', 'rejection_reason' => $reason, 'processed_by' => $user->id]);
        $this->audit->record('DISBURSEMENT_REJECTED', $disb, ['status' => 'DIAJUKAN'], ['status' => 'DITOLAK', 'reason' => $reason], 'PROSES', $disb->application_id, $user);

        return $disb;
    }

    /** Pengecualian 100% (Pasal 9 ayat 3): OPD mengajukan dengan dasar & alasan, BPKAD memberi otorisasi. */
    public function requestFullException(GrantApplication $app, User $user, string $basis, string $reason): void
    {
        if (! $user->hasRole('OPD') || $user->organization_id !== $app->opd_id) {
            throw new AuthorizationException('Hanya OPD pemilik urusan yang dapat mengajukan pengecualian.');
        }
        $x = Validator::make(['basis' => $basis, 'reason' => $reason], [
            'basis' => 'required|in:INSTANSI_VERTIKAL,EVENT', 'reason' => 'required|string|min:15',
        ], [], ['basis' => 'Dasar pengecualian', 'reason' => 'Alasan'])->validate();

        if ($app->status !== Status::NPHD_SIGNED || $app->disbursements()->whereIn('status', ['DIAJUKAN', 'DISALURKAN'])->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Pengecualian hanya dapat diajukan sebelum ada penyaluran apa pun.']);
        }
        $app->update(['full_exception_reason' => '['.$x['basis'].'] '.$x['reason'], 'full_exception_by' => null, 'full_exception_at' => null]);
        $this->audit->record('FULL_EXCEPTION_REQUESTED', $app, null, $x, 'PROSES', $app->id, $user);
    }

    public function authorizeFullException(GrantApplication $app, User $user, bool $approve, ?string $note = null): void
    {
        if (! $user->hasRole('BPKAD')) {
            throw new AuthorizationException('Hanya BPKAD yang berwenang memberi otorisasi pengecualian.');
        }
        if (blank($app->full_exception_reason) || $app->full_exception_by) {
            throw ValidationException::withMessages(['workflow' => 'Tidak ada pengajuan pengecualian yang menunggu otorisasi.']);
        }
        if ($app->disbursements()->whereIn('status', ['DIAJUKAN', 'DISALURKAN'])->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Sudah ada penyaluran; skema tidak dapat diubah.']);
        }
        if ($approve) {
            $app->update(['disbursement_scheme' => 'FULL', 'full_exception_by' => $user->id, 'full_exception_at' => now()]);
        } else {
            $app->update(['full_exception_reason' => null]);
        }
        $this->audit->record($approve ? 'FULL_EXCEPTION_AUTHORIZED' : 'FULL_EXCEPTION_DENIED', $app, null, ['note' => $note], 'PROSES', $app->id, $user);
    }
}
