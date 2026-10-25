<?php

namespace App\Enums;

/**
 * Status siklus satu permohonan hibah (Blueprint Bab 8).
 */
enum Status: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case ADMIN_CHECK = 'ADMIN_CHECK';
    case TECH_CHECK = 'TECH_CHECK';
    case RECOMMENDED = 'RECOMMENDED';
    case TAPD_REVIEW = 'TAPD_REVIEW';
    case APPROVED = 'APPROVED';
    case BUDGETED = 'BUDGETED';
    case NPHD_SIGNED = 'NPHD_SIGNED';
    case DISBURSED = 'DISBURSED';
    case LPJ_REVIEW = 'LPJ_REVIEW';
    case CLOSED = 'CLOSED';
    // jalur pengecualian
    case RETURNED = 'RETURNED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::SUBMITTED => 'Diajukan',
            self::ADMIN_CHECK => 'Verifikasi Administrasi',
            self::TECH_CHECK => 'Verifikasi Teknis',
            self::RECOMMENDED => 'Direkomendasi',
            self::TAPD_REVIEW => 'Pertimbangan TAPD',
            self::APPROVED => 'Ditetapkan',
            self::BUDGETED => 'Dianggarkan',
            self::NPHD_SIGNED => 'NPHD Ditandatangani',
            self::DISBURSED => 'Disalurkan / Diserahkan',
            self::LPJ_REVIEW => 'LPJ Diperiksa',
            self::CLOSED => 'Selesai',
            self::RETURNED => 'Dikembalikan',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function phase(): string
    {
        return match ($this) {
            self::DRAFT, self::SUBMITTED, self::RETURNED => 'Pengajuan',
            self::ADMIN_CHECK, self::TECH_CHECK, self::RECOMMENDED => 'Penilaian',
            self::TAPD_REVIEW, self::APPROVED, self::BUDGETED, self::NPHD_SIGNED => 'Penetapan',
            self::DISBURSED => 'Penyaluran',
            self::LPJ_REVIEW, self::CLOSED => 'Pertanggungjawaban',
            self::REJECTED, self::CANCELLED => 'Dihentikan',
        };
    }

    /** Kelas warna badge di UI. */
    public function tone(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED, self::ADMIN_CHECK, self::TECH_CHECK => 'blue',
            self::RECOMMENDED, self::TAPD_REVIEW => 'violet',
            self::APPROVED, self::BUDGETED, self::NPHD_SIGNED => 'gold',
            self::DISBURSED, self::LPJ_REVIEW => 'teal',
            self::CLOSED => 'green',
            self::RETURNED => 'amber',
            self::REJECTED, self::CANCELLED => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CLOSED, self::REJECTED, self::CANCELLED], true);
    }

    /** Pemohon hanya boleh mengubah isian/dokumen pada status ini. */
    public function isEditableByApplicant(): bool
    {
        return in_array($this, [self::DRAFT, self::RETURNED], true);
    }

    /** Urutan alur utama (tanpa jalur pengecualian) untuk tampilan timeline. */
    public static function mainFlow(): array
    {
        return [
            self::DRAFT, self::SUBMITTED, self::ADMIN_CHECK, self::TECH_CHECK, self::RECOMMENDED,
            self::TAPD_REVIEW, self::APPROVED, self::BUDGETED, self::NPHD_SIGNED,
            self::DISBURSED, self::LPJ_REVIEW, self::CLOSED,
        ];
    }

    public function order(): int
    {
        $i = array_search($this, self::mainFlow(), true);

        return $i === false ? -1 : $i;
    }

    /** Peran yang sedang memegang bola pada status ini. */
    public function responsibleRoles(): array
    {
        return match ($this) {
            self::DRAFT, self::RETURNED, self::DISBURSED => ['PEMOHON'],
            self::SUBMITTED, self::ADMIN_CHECK, self::LPJ_REVIEW => ['OPD'],
            self::TECH_CHECK => ['VERIFIKATOR', 'OPD'],
            self::RECOMMENDED => ['TAPD'],
            self::TAPD_REVIEW => ['TAPD', 'BUPATI'],
            self::APPROVED => ['BPKAD'],
            self::BUDGETED => ['OPD', 'PEMOHON'],
            self::NPHD_SIGNED => ['OPD', 'BPKAD'],
            default => [],
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
