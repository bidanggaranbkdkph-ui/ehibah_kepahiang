<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\AccountabilityReport;
use App\Models\FundReturn;
use App\Models\GrantApplication;
use App\Models\MonitoringRecord;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * LPJ (Perbup Pasal 16-21): laporan tahap/akhir, realisasi, sisa dana & setoran, monev.
 */
class AccountabilityService
{
    public function __construct(private AuditService $audit, private GrantCalculator $calc, private DocumentChecklist $docs, private NotificationService $notifier)
    {
    }

    /** Simpan draf laporan (TAHAP_1 | AKHIR) beserta rincian realisasi. Total dihitung server, bukan dari input. */
    public function saveDraft(GrantApplication $app, User $user, string $type, array $data, array $expenses): AccountabilityReport
    {
        $this->assertApplicant($app, $user);
        if ($app->status !== Status::DISBURSED) {
            throw ValidationException::withMessages(['workflow' => 'Laporan hanya dapat disusun setelah hibah disalurkan dan sebelum LPJ akhir diajukan.']);
        }
        if (! in_array($type, ['TAHAP_1', 'AKHIR'], true)) {
            throw ValidationException::withMessages(['report_type' => 'Jenis laporan tidak valid.']);
        }
        if ($type === 'TAHAP_1' && $app->disbursement_scheme !== 'STAGED') {
            throw ValidationException::withMessages(['report_type' => 'Laporan tahap I hanya untuk penyaluran bertahap.']);
        }
        $x = Validator::make($data, [
            'summary' => 'nullable|string', 'activity_result' => 'nullable|string',
            'period_start' => 'nullable|date', 'period_end' => 'nullable|date|after_or_equal:period_start',
            'bpk_audit_date' => 'nullable|date',
        ], [], ['summary' => 'Uraian kegiatan', 'activity_result' => 'Hasil kegiatan'])->validate();

        $lines = collect($expenses)->filter(fn ($e) => filled($e['description'] ?? null) || filled($e['amount'] ?? null))->values();
        foreach ($lines as $i => $l) {
            if (! filled($l['description'] ?? null) || ! is_numeric($l['amount'] ?? null) || (int) $l['amount'] <= 0 || empty($l['spent_on'])) {
                throw ValidationException::withMessages(['expenses' => 'Baris realisasi '.($i + 1).' tidak lengkap (tanggal, uraian, nilai > 0).']);
            }
        }
        $total = (int) $lines->sum(fn ($l) => (int) $l['amount']);
        if ($app->isMoney() && $total > $app->disbursedTotal()) {
            throw ValidationException::withMessages(['expenses' => 'Total realisasi '.Fmt::rupiah($total).' melebihi dana yang disalurkan ('.Fmt::rupiah($app->disbursedTotal()).').']);
        }

        return DB::transaction(function () use ($app, $user, $type, $x, $lines, $total) {
            $report = AccountabilityReport::query()->where('application_id', $app->id)->where('report_type', $type)
                ->whereIn('review_status', ['DRAFT', 'PERLU_PERBAIKAN'])->latest('id')->first()
                ?? new AccountabilityReport(['application_id' => $app->id, 'report_type' => $type, 'review_status' => 'DRAFT']);

            $report->fill($x + [
                'due_date' => $app->lpj_due_date, 'total_realization' => $total,
                'remaining_funds' => $app->isMoney() ? $this->calc->remainingFunds($app->disbursedTotal(), $total) : 0,
            ]);
            if ($report->review_status === 'PERLU_PERBAIKAN') {
                $report->review_status = 'DRAFT';
            }
            $report->save();

            $report->expenses()->delete();
            foreach ($lines as $l) {
                $report->expenses()->create([
                    'spent_on' => $l['spent_on'], 'description' => $l['description'],
                    'amount' => (int) $l['amount'], 'receipt_ref' => $l['receipt_ref'] ?? null,
                ]);
            }
            $this->audit->record('REPORT_SAVED', $report, null, ['type' => $type, 'total' => $total], 'PROSES', $app->id, $user);

            return $report;
        });
    }

    /** Tanggal kegiatan selesai aktual -> tenggat LPJ (Pasal 19). */
    public function setActivityCompleted(GrantApplication $app, User $user, string $date): GrantApplication
    {
        $this->assertApplicant($app, $user);
        $d = \Illuminate\Support\Carbon::parse($date);
        if ($d->isFuture()) {
            throw ValidationException::withMessages(['activity_completed_at' => 'Tanggal selesai kegiatan tidak boleh di masa depan.']);
        }
        $due = $this->calc->lpjDueDate($d, $app->fiscalYear->year);
        $old = $app->only(['activity_completed_at', 'lpj_due_date']);
        $app->update(['activity_completed_at' => $d, 'lpj_due_date' => $due]);
        $this->audit->record('LPJ_DUE_COMPUTED', $app, $old, ['activity_completed_at' => $d->toDateString(), 'lpj_due_date' => $due->toDateString()], 'PROSES', $app->id, $user);

        return $app;
    }

    /** Laporan tahap I diajukan pemohon -> diperiksa OPD -> membuka tahap II. */
    public function submitInterim(AccountabilityReport $report, User $user): AccountabilityReport
    {
        $app = $report->application;
        $this->assertApplicant($app, $user);
        if ($report->report_type !== 'TAHAP_1' || $report->review_status !== 'DRAFT') {
            throw ValidationException::withMessages(['workflow' => 'Hanya draf laporan tahap I yang dapat diajukan.']);
        }
        $problems = [];
        if (blank($report->summary)) {
            $problems[] = 'Uraian laporan wajib diisi.';
        }
        if ($report->expenses()->count() === 0) {
            $problems[] = 'Rincian realisasi belum diisi.';
        }
        foreach ($this->docs->missing($app, 'LPJ', ['report', $report->id]) as $m) {
            $problems[] = "Dokumen laporan belum diunggah: $m.";
        }
        if ($problems) {
            throw ValidationException::withMessages(['workflow' => $problems]);
        }
        $report->update(['review_status' => 'DIAJUKAN', 'submitted_at' => now(), 'submitted_by' => $user->id]);
        $this->audit->record('REPORT_SUBMITTED', $report, null, ['type' => 'TAHAP_1'], 'PROSES', $app->id, $user);
        $this->notifier->send($app, ['OPD'], 'Laporan tahap I diajukan', 'Periksa laporan penggunaan tahap I untuk membuka penyaluran tahap II.', 'info', $user->id);

        return $report;
    }

    public function reviewInterim(AccountabilityReport $report, User $user, string $result, ?string $notes): AccountabilityReport
    {
        $app = $report->application;
        if (! $user->hasRole('OPD') || $user->organization_id !== $app->opd_id) {
            throw new AuthorizationException('Hanya OPD pemilik urusan yang memeriksa laporan.');
        }
        if ($report->report_type !== 'TAHAP_1' || $report->review_status !== 'DIAJUKAN') {
            throw ValidationException::withMessages(['workflow' => 'Tidak ada laporan tahap I yang menunggu pemeriksaan.']);
        }
        if (! in_array($result, ['DITERIMA', 'PERLU_PERBAIKAN'], true)) {
            throw ValidationException::withMessages(['result' => 'Hasil pemeriksaan tidak valid.']);
        }
        if ($result === 'PERLU_PERBAIKAN' && blank($notes)) {
            throw ValidationException::withMessages(['notes' => 'Catatan kekurangan wajib diisi.']);
        }
        $report->update(['review_status' => $result, 'review_notes' => $notes, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        $this->audit->record('REPORT_REVIEWED', $report, null, ['result' => $result, 'notes' => $notes], 'PROSES', $app->id, $user);
        $this->notifier->send($app, ['PEMOHON'], 'Hasil pemeriksaan laporan tahap I',
            $result === 'DITERIMA' ? 'Laporan tahap I diterima. Penyaluran tahap II dapat diproses.' : 'Laporan perlu diperbaiki: '.$notes, $result === 'DITERIMA' ? 'success' : 'warning', $user->id);

        return $report;
    }

    /** Setoran sisa dana ke kas daerah (Pasal 20) + pemberitahuan ke SKPD dan tembusan BUD. */
    public function recordReturn(GrantApplication $app, User $user, array $data): FundReturn
    {
        if (! $user->hasRole('OPD', 'PEMOHON') || ! in_array($user->organization_id, [$app->opd_id, $app->applicant_org_id], true)) {
            throw new AuthorizationException('Anda tidak berwenang mencatat setoran sisa dana.');
        }
        if (! $app->isMoney() || ! in_array($app->status, [Status::DISBURSED, Status::LPJ_REVIEW], true)) {
            throw ValidationException::withMessages(['workflow' => 'Setoran sisa dana dicatat untuk hibah uang yang telah disalurkan.']);
        }
        $x = Validator::make($data, [
            'amount' => 'required|integer|min:1', 'returned_on' => 'required|date|before_or_equal:today', 'kas_ref' => 'required|string|max:80',
        ], [], ['amount' => 'Nilai setoran', 'returned_on' => 'Tanggal setor', 'kas_ref' => 'Referensi kas daerah (STS)'])->validate();

        $maxReturn = $app->disbursedTotal() - $app->returnedTotal();
        if ($x['amount'] > $maxReturn) {
            throw ValidationException::withMessages(['amount' => 'Setoran melebihi dana yang disalurkan.']);
        }

        return DB::transaction(function () use ($app, $user, $x) {
            $report = $app->reports()->where('report_type', 'AKHIR')->latest('id')->first();
            $r = FundReturn::create($x + [
                'application_id' => $app->id, 'report_id' => $report?->id, 'recorded_by' => $user->id, 'notified_at' => now(),
            ]);
            $this->audit->record('FUND_RETURNED', $r, null, ['amount' => $r->amount, 'kas_ref' => $r->kas_ref], 'PROSES', $app->id, $user);
            $this->notifier->send($app, ['OPD', 'BPKAD'], 'Setoran sisa dana dicatat',
                'Sisa dana '.Fmt::rupiah($r->amount).' disetor ke kas daerah (ref '.$r->kas_ref.'). Tembusan BUD.', 'info', $user->id);

            return $r;
        });
    }

    /** Monitoring & evaluasi (Pasal 21). */
    public function recordMonitoring(GrantApplication $app, User $user, array $data): MonitoringRecord
    {
        if (! \App\Support\Permission::allows($user, 'monev', 'P')) {
            throw new AuthorizationException('Anda tidak berwenang mengisi monitoring & evaluasi.');
        }
        if ($user->hasRole('OPD') && ! $user->hasRole('INSPEKTORAT') && $user->organization_id !== $app->opd_id) {
            throw new AuthorizationException('Monitoring hanya untuk usulan di OPD Anda.');
        }
        if (! in_array($app->status, [Status::DISBURSED, Status::LPJ_REVIEW, Status::CLOSED], true)) {
            throw ValidationException::withMessages(['workflow' => 'Monitoring dilakukan setelah hibah disalurkan.']);
        }
        $x = Validator::make($data, [
            'type' => 'required|in:MONEV,EVALUASI', 'visited_at' => 'required|date|before_or_equal:today',
            'findings' => 'required|string|min:10', 'recommendation' => 'nullable|string', 'follow_up' => 'nullable|string',
            'follow_up_due' => 'nullable|date|after_or_equal:visited_at', 'follow_up_status' => 'nullable|in:OPEN,SELESAI',
        ], [], ['findings' => 'Temuan', 'visited_at' => 'Tanggal'])->validate();

        $m = MonitoringRecord::create($x + [
            'application_id' => $app->id, 'officer_id' => $user->id,
            'follow_up_status' => $x['follow_up_status'] ?? (filled($x['follow_up'] ?? null) ? 'OPEN' : 'SELESAI'),
            // Pasal 21: hasil disampaikan ke Bupati, tembusan Inspektorat — tercatat sebagai bukti distribusi
            'sent_to_bupati_at' => now(), 'sent_to_inspektorat_at' => now(),
        ]);
        $this->audit->record('MONITORING_RECORDED', $m, null, ['type' => $m->type, 'follow_up_status' => $m->follow_up_status], 'PROSES', $app->id, $user);
        $this->notifier->send($app, ['BUPATI', 'INSPEKTORAT'], 'Hasil monitoring hibah', 'Laporan monitoring baru untuk '.($app->reg_number ?? 'usulan').' (tembusan Inspektorat).', 'info', $user->id);

        return $m;
    }

    private function assertApplicant(GrantApplication $app, User $user): void
    {
        if (! $user->hasRole('PEMOHON') || $user->organization_id !== $app->applicant_org_id) {
            throw new AuthorizationException('Hanya pemohon pemilik usulan yang menyusun laporan.');
        }
    }
}
