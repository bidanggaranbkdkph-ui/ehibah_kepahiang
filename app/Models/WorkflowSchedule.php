<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WorkflowSchedule extends Model
{
    protected $fillable = [
        'fiscal_year_id',
        'stage_code',
        'stage_name',
        'sequence',
        'regulation_ref',
        'start_date',
        'end_date',
        'control_mode',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
    ];

    public const STAGES = [
        'SUBMISSION' => [
            'sequence'       => 1,
            'name'           => 'Penyampaian Usulan / Ajuan Dokumen',
            'regulation_ref' => 'Perbup Hibah No. 25/2025 & Permendagri 77/2020 (Pra-RKPD Tahun N-1)',
        ],
        'VERIFICATION' => [
            'sequence'       => 2,
            'name'           => 'Verifikasi Administrasi, Teknis & Rekomendasi OPD',
            'regulation_ref' => 'Perbup Hibah No. 25/2025 & SK Sekda Tim Verifikasi',
        ],
        'TAPD_REVIEW' => [
            'sequence'       => 3,
            'name'           => 'Pertimbangan TAPD',
            'regulation_ref' => 'Permendagri 77/2020 (Pembahasan KUA-PPAS / RKA)',
        ],
        'APPROVAL' => [
            'sequence'       => 4,
            'name'           => 'Penetapan Kepala Daerah (SK Bupati)',
            'regulation_ref' => 'Perkada Penjabaran APBD & SK Bupati Daftar Penerima Hibah',
        ],
        'BUDGETING' => [
            'sequence'       => 5,
            'name'           => 'Penganggaran & Sinkronisasi DPA',
            'regulation_ref' => 'PP 12/2019 & Perda APBD (Pengesahan DPA-SKPD)',
        ],
        'NPHD' => [
            'sequence'       => 6,
            'name'           => 'Pengesahan & Penandatanganan NPHD (OPD Teknis)',
            'regulation_ref' => 'Perbup Hibah No. 25/2025 (Wajib sebelum pencairan)',
        ],
        'DISBURSEMENT' => [
            'sequence'       => 7,
            'name'           => 'Penyaluran Hibah (SP2D Uang / BAST Barang)',
            'regulation_ref' => 'Perbup Sistem & Prosedur Pengelolaan Keuangan Daerah',
        ],
        'ACCOUNTABILITY' => [
            'sequence'       => 8,
            'name'           => 'Pertanggungjawaban (LPJ & Pengembalian Sisa Dana)',
            'regulation_ref' => 'Permendagri 77/2020 & Perbup 25/2025 (Maks. 10 Januari Tahun N+1)',
        ],
        'AUDIT' => [
            'sequence'       => 9,
            'name'           => 'Monitoring, Evaluasi & Audit Pengawasan',
            'regulation_ref' => 'PP 12/2019 & Pengawasan APIP (Inspektorat Daerah)',
        ],
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Mengecek apakah tahapan saat ini terbuka untuk diproses
     */
    public function isOpen(?Carbon $now = null): bool
    {
        $now = $now ?: now();

        if ($this->control_mode === 'FORCE_OPEN') {
            return true;
        }

        if ($this->control_mode === 'FORCE_CLOSED') {
            return false;
        }

        // Mode AUTO: Cek rentang start_date dan end_date
        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    /**
     * Pesan status informatif untuk ditampilkan di UI / Alert
     */
    public function statusMessage(): string
    {
        if ($this->control_mode === 'FORCE_OPEN') {
            return 'Dibuka Manual oleh Admin';
        }

        if ($this->control_mode === 'FORCE_CLOSED') {
            return 'Ditutup Manual oleh Admin';
        }

        $now = now();
        if ($this->start_date && $now->lt($this->start_date)) {
            return 'Belum Dibuka (Mulai: ' . $this->start_date->translatedFormat('d M Y H:i') . ')';
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return 'Melewati Batas Waktu (Tutup: ' . $this->end_date->translatedFormat('d M Y H:i') . ')';
        }

        if ($this->end_date) {
            return 'Terbuka (Batas akhir: ' . $this->end_date->translatedFormat('d M Y H:i') . ')';
        }

        return 'Terbuka';
    }
}