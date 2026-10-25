<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Decision;
use App\Models\Decree;
use App\Models\GrantApplication;
use App\Models\Recommendation;
use App\Models\StatusHistory;
use App\Models\TapdReview;
use App\Models\User;
use App\Models\Verification;
use App\Support\Fmt;
use App\Support\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Mesin alur status permohonan (Blueprint Bab 5, 8 dan SOP Bab 4).
 *
 * Setiap perpindahan status:
 *   1. hanya dari status yang diizinkan, oleh peran + organisasi yang berwenang,
 *   2. harus lolos guard (prasyarat regulasi/dokumen/anggaran) di sisi server,
 *   3. berjalan dalam satu transaksi dengan penguncian baris,
 *   4. tercatat di status_histories + audit_logs, lalu memicu notifikasi.
 */
class WorkflowService
{
    /**
     * scope: applicant = org pemohon usulan | opd = OPD pemilik usulan | any = lintas organisasi
     * note : alasan wajib (penolakan/pengembalian/pembatalan)
     * ui   : false = tidak ditampilkan sebagai tombol (dipicu proses lain)
     */
    public const TRANSITIONS = [
        'submit'           => ['label' => 'Ajukan usulan', 'from' => ['DRAFT', 'RETURNED'], 'to' => 'SUBMITTED', 'roles' => ['PEMOHON'], 'scope' => 'applicant', 'tone' => 'primary'],
        'applicant_cancel' => ['label' => 'Batalkan usulan', 'from' => ['DRAFT', 'SUBMITTED', 'RETURNED'], 'to' => 'CANCELLED', 'roles' => ['PEMOHON'], 'scope' => 'applicant', 'note' => true, 'tone' => 'danger'],
        'start_admin'      => ['label' => 'Mulai verifikasi administrasi', 'from' => ['SUBMITTED'], 'to' => 'ADMIN_CHECK', 'roles' => ['OPD'], 'scope' => 'opd', 'tone' => 'primary'],
        'admin_pass'       => ['label' => 'Lulus verifikasi administrasi', 'from' => ['ADMIN_CHECK'], 'to' => 'TECH_CHECK', 'roles' => ['OPD'], 'scope' => 'opd', 'tone' => 'primary', 'form' => 'admin_checklist'],
        'admin_return'     => ['label' => 'Kembalikan ke pemohon', 'from' => ['ADMIN_CHECK'], 'to' => 'RETURNED', 'roles' => ['OPD'], 'scope' => 'opd', 'note' => true, 'tone' => 'warning'],
        'admin_reject'     => ['label' => 'Tolak usulan', 'from' => ['ADMIN_CHECK'], 'to' => 'REJECTED', 'roles' => ['OPD'], 'scope' => 'opd', 'note' => true, 'tone' => 'danger'],
        'tech_return'      => ['label' => 'Kembalikan untuk perbaikan', 'from' => ['TECH_CHECK'], 'to' => 'RETURNED', 'roles' => ['VERIFIKATOR'], 'scope' => 'any', 'note' => true, 'tone' => 'warning'],
        'tech_reject'      => ['label' => 'Tolak (tidak layak)', 'from' => ['TECH_CHECK'], 'to' => 'REJECTED', 'roles' => ['VERIFIKATOR'], 'scope' => 'any', 'note' => true, 'tone' => 'danger'],
        'recommend'        => ['label' => 'Terbitkan rekomendasi', 'from' => ['TECH_CHECK'], 'to' => 'RECOMMENDED', 'roles' => ['OPD'], 'scope' => 'opd', 'tone' => 'primary', 'form' => 'recommendation'],
        'tapd_start'       => ['label' => 'Ambil untuk pertimbangan TAPD', 'from' => ['RECOMMENDED'], 'to' => 'TAPD_REVIEW', 'roles' => ['TAPD'], 'scope' => 'any', 'tone' => 'primary'],
        'tapd_reject'      => ['label' => 'Tolak (pertimbangan TAPD)', 'from' => ['RECOMMENDED', 'TAPD_REVIEW'], 'to' => 'REJECTED', 'roles' => ['TAPD'], 'scope' => 'any', 'note' => true, 'tone' => 'danger'],
        'tapd_cancel'      => ['label' => 'Batalkan (anggaran tidak tersedia)', 'from' => ['RECOMMENDED', 'TAPD_REVIEW'], 'to' => 'CANCELLED', 'roles' => ['TAPD'], 'scope' => 'any', 'note' => true, 'tone' => 'danger'],
        'approve'          => ['label' => 'Tetapkan penerima hibah', 'from' => ['TAPD_REVIEW'], 'to' => 'APPROVED', 'roles' => ['BUPATI'], 'scope' => 'any', 'tone' => 'primary', 'form' => 'approval'],
        'bupati_reject'    => ['label' => 'Tolak usulan', 'from' => ['TAPD_REVIEW'], 'to' => 'REJECTED', 'roles' => ['BUPATI'], 'scope' => 'any', 'note' => true, 'tone' => 'danger'],
        'confirm_budget'   => ['label' => 'Konfirmasi dianggarkan (DPA)', 'from' => ['APPROVED'], 'to' => 'BUDGETED', 'roles' => ['BPKAD'], 'scope' => 'any', 'tone' => 'primary', 'form' => 'budget_confirm'],
        'budget_cancel'    => ['label' => 'Batalkan (anggaran tidak tersedia)', 'from' => ['APPROVED', 'BUDGETED'], 'to' => 'CANCELLED', 'roles' => ['BPKAD'], 'scope' => 'any', 'note' => true, 'tone' => 'danger'],
        'sign_nphd'        => ['label' => 'Konfirmasi NPHD ditandatangani', 'from' => ['BUDGETED'], 'to' => 'NPHD_SIGNED', 'roles' => ['OPD'], 'scope' => 'opd', 'tone' => 'primary', 'form' => 'nphd_sign'],
        'mark_disbursed'   => ['label' => 'Tandai disalurkan', 'from' => ['NPHD_SIGNED'], 'to' => 'DISBURSED', 'roles' => ['BPKAD'], 'scope' => 'any', 'ui' => false],
        'submit_lpj'       => ['label' => 'Ajukan LPJ akhir', 'from' => ['DISBURSED'], 'to' => 'LPJ_REVIEW', 'roles' => ['PEMOHON'], 'scope' => 'applicant', 'tone' => 'primary'],
        'lpj_return'       => ['label' => 'Kembalikan LPJ untuk perbaikan', 'from' => ['LPJ_REVIEW'], 'to' => 'DISBURSED', 'roles' => ['OPD'], 'scope' => 'opd', 'note' => true, 'tone' => 'warning'],
        'close'            => ['label' => 'Terima LPJ & tutup hibah', 'from' => ['LPJ_REVIEW'], 'to' => 'CLOSED', 'roles' => ['OPD'], 'scope' => 'opd', 'tone' => 'primary'],
    ];

    /** Kriteria hibah (Perbup Pasal 2) yang dinilai OPD pada verifikasi administrasi. */
    public const CRITERIA = [
        'spesifik'        => 'Peruntukan hibah spesifik (jelas sasaran & kegiatan)',
        'tidak_wajib'     => 'Tidak wajib dan tidak mengikat',
        'tidak_rutin'     => 'Tidak diberikan secara terus-menerus/rutin setiap tahun (atau pengecualian beralasan)',
        'bermanfaat'      => 'Bermanfaat bagi pemerintah daerah dan masyarakat',
        'memenuhi_syarat' => 'Penerima memenuhi persyaratan kategori penerima',
        'bersedia_nphd'   => 'Penerima bersedia menandatangani NPHD',
    ];

    public const TECH_SCORES = [
        'relevansi' => 'Relevansi dengan urusan & prioritas daerah',
        'rab'       => 'Kewajaran RAB / daftar barang',
        'manfaat'   => 'Manfaat & dampak bagi masyarakat',
        'kesiapan'  => 'Kesiapan pelaksanaan & kapasitas pemohon',
    ];

    public function __construct(
        private AuditService $audit,
        private BudgetService $budget,
        private DocumentChecklist $docs,
        private NotificationService $notifier,
        private GrantCalculator $calc,
        private StageScheduleService $schedules,
    ) {
    }

    // ------------------------------------------------------------------
    // Pertanyaan untuk UI
    // ------------------------------------------------------------------

    /** @return array<string, array> aksi yang boleh dilakukan pengguna saat ini */
    public function availableActions(GrantApplication $app, User $user): array
    {
        $out = [];
        foreach (self::TRANSITIONS as $action => $def) {
            if (($def['ui'] ?? true) && in_array($app->status->value, $def['from'], true) && $this->isAuthorized($app, $user, $def)) {
                $out[$action] = $def;
            }
        }

        return $out;
    }

    public function isAuthorized(GrantApplication $app, User $user, array $def): bool
    {
        if (! $user->is_active || ! $user->hasRole(...$def['roles'])) {
            return false;
        }

        return match ($def['scope']) {
            'applicant' => $user->organization_id !== null && $user->organization_id === $app->applicant_org_id,
            'opd'       => $user->organization_id !== null && $user->organization_id === $app->opd_id,
            default     => true,
        };
    }

    /** Prasyarat yang belum terpenuhi untuk suatu aksi (tanpa mengubah data). */
    public function blockers(GrantApplication $app, string $action): array
    {
        $app->loadMissing(['items', 'grantType', 'category', 'regulation', 'fiscalYear', 'applicant']);

        $errors = [];

        if ($stageError = $this->schedules->stageBlockerMessage($app->fiscal_year_id, $action)) {
            $errors[] = $stageError;
        }

        $guard = 'guard'.str_replace('_', '', ucwords($action, '_'));
        if (method_exists($this, $guard)) {
            $errors = array_merge($errors, array_values($this->$guard($app)));
        }

        return $errors;
    }

    // ------------------------------------------------------------------
    // Eksekusi transisi
    // ------------------------------------------------------------------

    /**
     * @param  array  $data  isian form (note, lock_version, dst.)
     *
     * @throws AuthorizationException|ValidationException
     */
    public function perform(GrantApplication $app, string $action, User $user, array $data = []): GrantApplication
    {
        $def = self::TRANSITIONS[$action] ?? throw new \InvalidArgumentException("Aksi tidak dikenal: $action");
        $notices = [];

        $app = DB::transaction(function () use ($app, $action, $def, $user, $data, &$notices) {
            $fresh = GrantApplication::query()->lockForUpdate()->findOrFail($app->id);
            $fresh->load(['items', 'grantType', 'category', 'regulation', 'fiscalYear', 'applicant', 'opd', 'allocation', 'nphds']);

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $fresh->lock_version) {
                throw ValidationException::withMessages(['workflow' => 'Data usulan telah diubah pengguna lain. Muat ulang halaman lalu ulangi.']);
            }
            if (! in_array($fresh->status->value, $def['from'], true)) {
                throw ValidationException::withMessages(['workflow' => sprintf(
                    'Aksi "%s" tidak dapat dilakukan pada status "%s".', $def['label'], $fresh->status->label()
                )]);
            }
            if (! $this->isAuthorized($fresh, $user, $def)) {
                throw new AuthorizationException('Anda tidak berwenang melakukan aksi ini.');
            }
            if (($def['note'] ?? false) && blank($data['note'] ?? null)) {
                throw ValidationException::withMessages(['note' => 'Alasan/catatan wajib diisi.']);
            }

            // Kunci eksekusi jika jadwal tahapan ditutup
            $this->schedules->assertActionStageOpen($fresh->fiscal_year_id, $action);

            $guard = 'guard'.str_replace('_', '', ucwords($action, '_'));
            if (method_exists($this, $guard) && ($errors = $this->$guard($fresh))) {
                throw ValidationException::withMessages(['workflow' => array_values($errors)]);
            }

            $from = $fresh->status;
            $apply = 'apply'.str_replace('_', '', ucwords($action, '_'));
            if (method_exists($this, $apply)) {
                $this->$apply($fresh, $user, $data);
            }

            $to = Status::from($def['to']);
            $fresh->setStatus($to);
            if (in_array($to, [Status::REJECTED], true)) {
                $fresh->rejection_reason = $data['note'] ?? null;
            }
            if ($to === Status::CANCELLED) {
                $fresh->cancellation_reason = $data['note'] ?? null;
            }
            if ($to === Status::CLOSED) {
                $fresh->closed_at = now();
            }
            if (in_array($to, [Status::REJECTED, Status::CANCELLED], true)) {
                $this->budget->release($fresh, $user, $to->label().': '.($data['note'] ?? ''));
            }
            $fresh->lock_version++;
            $fresh->save();

            StatusHistory::create([
                'application_id' => $fresh->id, 'from_status' => $from->value, 'to_status' => $to->value,
                'action' => $action, 'user_id' => $user->id, 'role_code' => $user->primaryRole(),
                'note' => $data['note'] ?? null, 'created_at' => now(),
            ]);
            $this->audit->record('STATUS_'.strtoupper($action), $fresh, ['status' => $from->value], [
                'status' => $to->value, 'note' => $data['note'] ?? null,
            ], 'PROSES', $fresh->id, $user);

            $notices = $this->noticesFor($fresh, $from, $to, $user, $data);

            return $fresh;
        });

        foreach ($notices as [$roles, $title, $message, $level]) {
            $this->notifier->send($app, $roles, $title, $message, $level, $user->id);
        }

        return $app;
    }

    /** Dipakai proses lain (penyaluran/penyerahan) untuk memajukan status tanpa tombol UI. */
    public function system(GrantApplication $app, string $action, User $user, array $data = []): GrantApplication
    {
        return $this->perform($app, $action, $user, $data);
    }

    private function noticesFor(GrantApplication $app, Status $from, Status $to, User $actor, array $data): array
    {
        $ref = $app->reg_number ?: 'usulan';
        $note = filled($data['note'] ?? null) ? ' Catatan: '.$data['note'] : '';
        $n = [];

        // pemohon selalu diberi tahu perubahan status usulannya
        if ($actor->id !== null && ! in_array($to, [Status::SUBMITTED], true)) {
            $level = match ($to) {
                Status::REJECTED, Status::CANCELLED => 'danger',
                Status::RETURNED => 'warning',
                Status::CLOSED, Status::APPROVED => 'success',
                default => 'info',
            };
            $n[] = [['PEMOHON'], 'Status usulan: '.$to->label(), "Usulan $ref berubah dari \"{$from->label()}\" menjadi \"{$to->label()}\".$note", $level];
        }

        // pihak berikutnya yang harus bertindak
        $next = array_values(array_diff($to->responsibleRoles(), ['PEMOHON']));
        if ($next && ! $to->isTerminal()) {
            $n[] = [$next, 'Perlu tindakan: '.$to->label(), "Usulan $ref menunggu tindakan Anda (status: {$to->label()}).", 'info'];
        }
        if ($to === Status::REJECTED || $to === Status::CANCELLED) {
            $n[] = [['OPD'], 'Usulan '.mb_strtolower($to->label()), "Usulan $ref {$to->label()}.$note", 'warning'];
        }
        if ($to === Status::CLOSED) {
            $n[] = [['INSPEKTORAT', 'BPKAD'], 'Hibah selesai', "Hibah $ref telah ditutup (LPJ diterima).", 'success'];
        }

        return $n;
    }

    // ------------------------------------------------------------------
    // GUARD — prasyarat server-side per aksi (mengembalikan daftar pesan)
    // ------------------------------------------------------------------

    private function guardSubmit(GrantApplication $a): array
    {
        $e = [];
        $a->loadMissing(['items', 'grantType', 'category', 'regulation', 'fiscalYear', 'applicant']);

        foreach (['title' => 'Judul usulan', 'background' => 'Latar belakang', 'purpose' => 'Maksud dan tujuan',
            'target' => 'Sasaran', 'program_activity' => 'Program/kegiatan', 'location' => 'Lokasi'] as $f => $label) {
            if (blank($a->$f)) {
                $e[] = "$label wajib diisi.";
            }
        }
        if (! $a->activity_start || ! $a->activity_end) {
            $e[] = 'Tanggal mulai dan selesai kegiatan wajib diisi.';
        } elseif ($a->activity_end->lt($a->activity_start)) {
            $e[] = 'Tanggal selesai kegiatan tidak boleh sebelum tanggal mulai.';
        }
        if (empty($a->committee)) {
            $e[] = 'Susunan organisasi/panitia pelaksana wajib diisi (Perbup Pasal 6).';
        }
        if ($a->items->isEmpty()) {
            $e[] = $a->isMoney() ? 'Rencana Anggaran Biaya (RAB) wajib diisi.' : 'Daftar barang yang diusulkan wajib diisi.';
        }
        if ($a->items->sum('subtotal') !== (int) $a->amount_requested || $a->amount_requested <= 0) {
            $e[] = 'Nilai usulan harus sama dengan jumlah RAB dan lebih dari nol.';
        }
        if (! $a->funding_declaration) {
            $e[] = 'Pernyataan tidak menerima pendanaan ganda (tumpang tindih APBN/sumber lain) wajib dicentang (Pasal 4).';
        }
        if ($a->is_recurring && blank($a->recurring_reason)) {
            $e[] = 'Hibah yang diberikan berulang wajib menyebut alasan pengecualian (Pasal 2).';
        }
        if ($a->grantType->scope !== 'HIBAH' || ! $a->grantType->is_active) {
            $e[] = 'Jenis bantuan ini bukan hibah (bansos berada di luar alur E-Hibah).';
        }
        if (! $a->regulation || ! $a->regulation->isActive() || ! $a->regulation->is_grant_basis) {
            $e[] = 'Dasar hukum yang dipilih tidak berlaku (dicabut/bukan dasar hibah). Gunakan regulasi aktif.';
        }
        if ($a->fiscalYear->status !== 'BERJALAN') {
            $e[] = 'Tahun anggaran '.$a->fiscalYear->year.' tidak sedang membuka pengajuan.';
        } else {
            $fy = $a->fiscalYear;
            if (($fy->submission_open && now()->lt($fy->submission_open)) || ($fy->submission_close && now()->startOfDay()->gt($fy->submission_close))) {
                $e[] = 'Di luar masa pengajuan ('.Fmt::date($fy->submission_open).' s.d. '.Fmt::date($fy->submission_close).').';
            }
        }
        $org = $a->applicant;
        if ($org->recipient_category_id !== $a->recipient_category_id) {
            $e[] = 'Kategori penerima usulan tidak sama dengan kategori lembaga pemohon.';
        }
        if ($a->isMoney() && (blank($org->bank_account_no) || blank($org->bank_account_name))) {
            $e[] = 'Data rekening lembaga (nomor & atas nama) wajib dilengkapi pada profil lembaga untuk hibah uang.';
        }
        if ($a->isParpol() && (blank($org->chair_name) || blank($org->secretary_name))) {
            $e[] = 'Nama ketua dan sekretaris partai politik wajib dilengkapi (Pasal 11).';
        }
        foreach ($this->docs->missing($a, 'PENGAJUAN') as $m) {
            $e[] = "Dokumen wajib belum diunggah: $m.";
        }

        return $e;
    }

    private function guardAdminPass(GrantApplication $a): array
    {
        $e = [];
        foreach ($this->docs->notVerified($a, 'PENGAJUAN') as $m) {
            $e[] = "Dokumen belum dinyatakan sesuai: $m.";
        }

        return $e;
    }

    private function guardRecommend(GrantApplication $a): array
    {
        $e = [];
        $admin = $a->verifications()->where('stage', 'ADMINISTRASI')->where('round', $a->revision_no)->where('result', 'LULUS')->latest('id')->first();
        $tech = $a->verifications()->where('stage', 'TEKNIS')->where('round', $a->revision_no)->latest('id')->first();

        if (! $admin) {
            $e[] = 'Verifikasi administrasi (checklist kriteria Pasal 2) belum lulus.';
        } else {
            foreach (self::CRITERIA as $k => $label) {
                if (empty($admin->checklist['criteria'][$k] ?? null)) {
                    $e[] = "Kriteria belum terpenuhi: $label.";
                }
            }
        }
        if (blank($a->purpose) || blank($a->program_activity)) {
            $e[] = 'Peruntukan hibah harus spesifik (maksud/tujuan dan program kegiatan terisi).';
        }
        if (! $tech) {
            $e[] = 'Verifikasi teknis belum dilakukan oleh Tim Verifikasi.';
        } elseif ($tech->result !== 'LULUS') {
            $e[] = 'Hasil verifikasi teknis: tidak layak (skor '.$tech->score.'). Usulan tidak dapat direkomendasikan.';
        }

        return $e;
    }

    private function guardApprove(GrantApplication $a): array
    {
        $e = [];
        if (! $a->tapdReview) {
            $e[] = 'Pertimbangan TAPD belum dicatat.';
        }
        $alloc = $a->allocation;
        if (! $alloc || $alloc->status !== 'RESERVED') {
            $e[] = 'Alokasi anggaran belum tersedia. Usulan tidak dapat ditetapkan sebelum dana dialokasikan dari pagu OPD.';
        }
        if (! $a->recommendation) {
            $e[] = 'Rekomendasi OPD tidak ditemukan.';
        }

        return $e;
    }

    private function guardConfirmBudget(GrantApplication $a): array
    {
        $alloc = $a->allocation;

        return (! $alloc || ! in_array($alloc->status, ['RESERVED', 'CONFIRMED'], true))
            ? ['Alokasi anggaran tidak ditemukan atau sudah dilepas.'] : [];
    }

    private function guardSignNphd(GrantApplication $a): array
    {
        $e = [];
        $n = $a->activeNphd();
        if (! $n) {
            return ['NPHD belum disusun.'];
        }
        if ($n->amount !== (int) $a->amount_approved) {
            $e[] = 'Nilai NPHD ('.Fmt::rupiah($n->amount).') tidak sama dengan nilai yang ditetapkan ('.Fmt::rupiah($a->amount_approved).').';
        }
        if (! $n->signer_user_id) {
            $e[] = 'Penandatangan NPHD (Kepala SKPD) belum ditetapkan.';
        } else {
            $signer = $n->signer;
            if (! $signer || $signer->organization_id !== $a->opd_id || ! $signer->hasRole('OPD') || ! preg_match('/^kepala/i', (string) $signer->position)) {
                $e[] = 'Penandatangan NPHD harus Kepala SKPD yang berwenang atas urusan ini (Pasal 8).';
            }
        }
        foreach ($this->docs->missing($a, 'NPHD') as $m) {
            $e[] = "Dokumen NPHD belum diunggah: $m.";
        }

        return $e;
    }

    private function guardSubmitLpj(GrantApplication $a): array
    {
        $e = [];
        $a->loadMissing('grantType');
        $report = $a->reports()->where('report_type', 'AKHIR')->latest('id')->first();

        if ($a->isMoney()) {
            if ($a->disbursedTotal() < (int) $a->amount_approved) {
                $e[] = 'Penyaluran belum tuntas ('.Fmt::rupiah($a->disbursedTotal()).' dari '.Fmt::rupiah($a->amount_approved).'). LPJ akhir diajukan setelah seluruh dana disalurkan.';
            }
        } elseif (($a->goodsDelivery?->status) !== 'DICATAT') {
            $e[] = 'Penyerahan barang belum selesai/dicatat sebagai aset.';
        }
        if (! $a->activity_completed_at) {
            $e[] = 'Tanggal kegiatan selesai belum diisi (dasar perhitungan tenggat LPJ).';
        }
        if (! $report) {
            return array_merge($e, ['Laporan pertanggungjawaban akhir belum disusun.']);
        }
        if (blank($report->summary) || blank($report->activity_result)) {
            $e[] = 'Uraian laporan kegiatan dan hasil yang dicapai wajib diisi (Lampiran E).';
        }
        if ($a->isMoney()) {
            if ($report->expenses()->count() === 0) {
                $e[] = 'Rincian realisasi penggunaan dana belum diisi.';
            }
            if ($report->total_realization > $a->disbursedTotal()) {
                $e[] = 'Realisasi melebihi dana yang disalurkan.';
            }
        }
        if ($a->isParpol() && ! $report->bpk_audit_date) {
            $e[] = 'Tanggal pemeriksaan BPK wajib diisi untuk LPJ partai politik (Pasal 18).';
        }
        foreach ($this->docs->missing($a, 'LPJ', ['report', $report->id]) as $m) {
            $e[] = "Dokumen LPJ belum diunggah: $m.";
        }

        return $e;
    }

    private function guardClose(GrantApplication $a): array
    {
        $e = [];
        $a->loadMissing('grantType');
        $report = $a->reports()->where('report_type', 'AKHIR')->latest('id')->first();

        foreach ($this->docs->notVerified($a, 'LPJ', $report ? ['report', $report->id] : null) as $m) {
            $e[] = "Dokumen LPJ belum dinyatakan sesuai: $m.";
        }
        if ($a->isMoney() && $report) {
            $remaining = $this->calc->remainingFunds($a->disbursedTotal(), $report->total_realization);
            if ($remaining > 0) {
                $returned = $a->returnedTotal();
                if ($returned < $remaining) {
                    $e[] = 'Sisa dana '.Fmt::rupiah($remaining).' belum disetor ke kas daerah (tercatat '.Fmt::rupiah($returned).'). Catat setoran & unggah bukti setor (Pasal 20).';
                }
                if ($a->fundReturns()->count() > 0 && ! $a->documents()->where('doc_type', 'BUKTI_SETOR')->where('is_current', true)->exists()) {
                    $e[] = 'Bukti setoran sisa dana ke kas daerah belum diunggah.';
                }
            }
        }
        if (Setting::bool('close.require_monitoring') && $a->monitoring()->count() === 0) {
            $e[] = 'Belum ada catatan monitoring & evaluasi (Pasal 21).';
        }
        if ($a->isMoney()) {
            if ($a->disbursements()->where('status', 'DISALURKAN')->whereNull('rkud_ref')->exists()) {
                $e[] = 'Arsip SKPD: bukti transfer dari RKUD belum lengkap pada data penyaluran (Pasal 15).';
            }
            if ($a->disbursements()->where('status', 'DISALURKAN')->count() === 0) {
                $e[] = 'Tidak ada penyaluran tercatat.';
            }
        } elseif (! $a->goodsDelivery || blank($a->goodsDelivery->bast_no)) {
            $e[] = 'Arsip SKPD: BAST belum tercatat (Pasal 17).';
        }
        if ($a->monitoring()->where('follow_up_status', 'OPEN')->exists()) {
            $e[] = 'Masih ada tindak lanjut hasil monitoring yang belum selesai.';
        }

        return $e;
    }

    // ------------------------------------------------------------------
    // APPLY — efek samping per aksi
    // ------------------------------------------------------------------

    private function applySubmit(GrantApplication $a, User $u, array $d): void
    {
        if (! $a->reg_number) {
            $fy = \App\Models\FiscalYear::query()->lockForUpdate()->find($a->fiscal_year_id);
            $fy->increment('last_seq');
            $a->reg_number = sprintf('EH/%d/%s/%04d', $fy->year, $a->opd->code, $fy->last_seq);
        }
        if ($a->status === Status::RETURNED) {
            $a->revision_no++;
        }
        $a->submitted_at = now();
    }

    private function applyAdminPass(GrantApplication $a, User $u, array $d): void
    {
        $crit = $d['criteria'] ?? [];
        $checklist = [];
        foreach (self::CRITERIA as $k => $label) {
            $checklist[$k] = filter_var($crit[$k] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        if (in_array(false, $checklist, true)) {
            throw ValidationException::withMessages(['criteria' => 'Seluruh kriteria Pasal 2 harus terpenuhi untuk dinyatakan lulus. Bila tidak, kembalikan atau tolak usulan.']);
        }
        Verification::create([
            'application_id' => $a->id, 'stage' => 'ADMINISTRASI', 'round' => $a->revision_no, 'result' => 'LULUS',
            'checklist' => ['criteria' => $checklist], 'notes' => $d['note'] ?? null,
            'verifier_id' => $u->id, 'verified_at' => now(),
        ]);
    }

    private function applyAdminReturn(GrantApplication $a, User $u, array $d): void
    {
        $this->recordNegative($a, $u, 'ADMINISTRASI', 'DIKEMBALIKAN', $d);
    }

    private function applyAdminReject(GrantApplication $a, User $u, array $d): void
    {
        $this->recordNegative($a, $u, 'ADMINISTRASI', 'DITOLAK', $d);
    }

    private function applyTechReturn(GrantApplication $a, User $u, array $d): void
    {
        $this->recordNegative($a, $u, 'TEKNIS', 'DIKEMBALIKAN', $d);
    }

    private function applyTechReject(GrantApplication $a, User $u, array $d): void
    {
        $this->recordNegative($a, $u, 'TEKNIS', 'DITOLAK', $d);
    }

    private function recordNegative(GrantApplication $a, User $u, string $stage, string $result, array $d): void
    {
        Verification::create([
            'application_id' => $a->id, 'stage' => $stage, 'round' => $a->revision_no, 'result' => $result,
            'notes' => $d['note'], 'verifier_id' => $u->id, 'verified_at' => now(),
        ]);
    }

    private function applyRecommend(GrantApplication $a, User $u, array $d): void
    {
        $v = Validator::make($d, [
            'letter_no'          => 'required|string|max:80',
            'letter_date'        => 'required|date',
            'amount_recommended' => 'required|integer|min:1|max:'.$a->amount_requested,
        ], [
            'amount_recommended.max' => 'Nilai rekomendasi tidak boleh melebihi nilai usulan ('.Fmt::rupiah($a->amount_requested).').',
        ], ['letter_no' => 'Nomor surat rekomendasi', 'letter_date' => 'Tanggal surat', 'amount_recommended' => 'Nilai rekomendasi']);
        $x = $v->validate();

        Recommendation::updateOrCreate(['application_id' => $a->id], [
            'opd_id' => $a->opd_id, 'letter_no' => $x['letter_no'], 'letter_date' => $x['letter_date'],
            'amount_recommended' => $x['amount_recommended'], 'notes' => $d['note'] ?? null,
            'issued_by' => $u->id, 'issued_at' => now(),
        ]);
        $a->amount_recommended = $x['amount_recommended'];
    }

    private function applyApprove(GrantApplication $a, User $u, array $d): void
    {
        $x = Validator::make($d, [
            'decree_id'       => 'required|exists:decrees,id',
            'amount_approved' => 'required|integer|min:1',
        ], [], ['decree_id' => 'Keputusan Bupati', 'amount_approved' => 'Nilai yang ditetapkan'])->validate();

        $decree = Decree::findOrFail($x['decree_id']);
        if ($decree->fiscal_year_id !== $a->fiscal_year_id) {
            throw ValidationException::withMessages(['decree_id' => 'Keputusan Bupati tidak untuk tahun anggaran usulan ini.']);
        }
        if (blank($decree->decree_no) || ! $decree->decree_date || blank($decree->perda_ref) || blank($decree->perkada_ref)) {
            throw ValidationException::withMessages(['decree_id' => 'Keputusan harus memuat nomor, tanggal, serta referensi Perda APBD dan Perkada penjabaran (Pasal 7).']);
        }
        if ($x['amount_approved'] > $a->amount_recommended) {
            throw ValidationException::withMessages(['amount_approved' => 'Nilai yang ditetapkan tidak boleh melebihi nilai rekomendasi ('.Fmt::rupiah($a->amount_recommended).').']);
        }
        if ($a->allocation->amount < $x['amount_approved']) {
            throw ValidationException::withMessages(['amount_approved' => 'Nilai ditetapkan melebihi alokasi anggaran ('.Fmt::rupiah($a->allocation->amount).').']);
        }

        Decision::updateOrCreate(['application_id' => $a->id], [
            'decree_id' => $decree->id, 'result' => 'SETUJU', 'amount_approved' => $x['amount_approved'],
            'notes' => $d['note'] ?? null, 'decided_by' => $u->id, 'decided_at' => now(),
        ]);
        $a->amount_approved = $x['amount_approved'];
        $a->decided_at = now();
        $this->budget->adjust($a, $u, $x['amount_approved']);
    }

    private function applyConfirmBudget(GrantApplication $a, User $u, array $d): void
    {
        $x = Validator::make($d, ['account_code' => 'required|string|max:40', 'dpa_ref' => 'required|string|max:80'],
            [], ['account_code' => 'Kode rekening', 'dpa_ref' => 'Referensi DPA'])->validate();
        $this->budget->confirm($a, $u, $x['account_code'], $x['dpa_ref']);
    }

    private function applySignNphd(GrantApplication $a, User $u, array $d): void
    {
        $x = Validator::make($d, [
            'signature_method' => 'required|in:BASAH,TTE',
            'signed_at'        => 'required|date|before_or_equal:today',
            'signature_ref'    => 'nullable|string|max:255',
        ], [], ['signature_method' => 'Metode tanda tangan', 'signed_at' => 'Tanggal penandatanganan'])->validate();

        $n = $a->activeNphd();
        $n->update([
            'status' => 'DITANDATANGANI', 'signature_method' => $x['signature_method'],
            'signature_ref' => $x['signature_ref'] ?? null, 'signed_at' => $x['signed_at'],
        ]);
    }

    private function applyMarkDisbursed(GrantApplication $a, User $u, array $d): void
    {
        // tenggat LPJ awal dihitung dari rencana selesai kegiatan; diperbarui saat tanggal selesai aktual diisi
        if ($a->activity_end && ! $a->lpj_due_date) {
            $a->lpj_due_date = $this->calc->lpjDueDate($a->activity_end, $a->fiscalYear->year);
        }
    }

    private function applySubmitLpj(GrantApplication $a, User $u, array $d): void
    {
        $report = $a->reports()->where('report_type', 'AKHIR')->latest('id')->first();
        $late = $a->lpj_due_date && now()->startOfDay()->gt($a->lpj_due_date);
        $report->update([
            'review_status' => 'DIAJUKAN', 'submitted_at' => now(), 'submitted_by' => $u->id, 'is_late' => $late,
            'remaining_funds' => $a->isMoney() ? $this->calc->remainingFunds($a->disbursedTotal(), $report->total_realization) : 0,
        ]);
        $a->lpj_overdue = false;
    }

    private function applyLpjReturn(GrantApplication $a, User $u, array $d): void
    {
        $report = $a->reports()->where('report_type', 'AKHIR')->latest('id')->first();
        $report?->update(['review_status' => 'PERLU_PERBAIKAN', 'review_notes' => $d['note'], 'reviewed_by' => $u->id, 'reviewed_at' => now()]);
    }

    private function applyClose(GrantApplication $a, User $u, array $d): void
    {
        $report = $a->reports()->where('report_type', 'AKHIR')->latest('id')->first();
        $report?->update(['review_status' => 'DITERIMA', 'review_notes' => $d['note'] ?? null, 'reviewed_by' => $u->id, 'reviewed_at' => now()]);
    }
}