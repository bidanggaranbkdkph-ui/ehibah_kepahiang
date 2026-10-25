<?php

namespace App\Services;

use App\Models\GrantApplication;
use App\Support\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Seluruh perhitungan hibah. Semua nilai uang berupa rupiah bulat (int).
 */
class GrantCalculator
{
    /**
     * Pembagian tahap penyaluran hibah uang (Perbup Pasal 9).
     * STAGED: tahap I = floor(nilai x %), tahap II = sisa (selisih pembulatan masuk tahap II).
     * FULL  : 100% sekaligus (pengecualian berotorisasi).
     *
     * @return array<int, array{stage:int, percentage:float, amount:int}>
     */
    public function stagePlan(int $total, string $scheme = 'STAGED'): array
    {
        if ($scheme === 'FULL') {
            return [1 => ['stage' => 1, 'percentage' => 100.0, 'amount' => $total]];
        }

        $p1 = max(1, min(99, Setting::int('disbursement.stage1_percent')));
        $a1 = intdiv($total * $p1, 100);

        return [
            1 => ['stage' => 1, 'percentage' => (float) $p1, 'amount' => $a1],
            2 => ['stage' => 2, 'percentage' => (float) (100 - $p1), 'amount' => $total - $a1],
        ];
    }

    /**
     * Batas waktu LPJ (Pasal 19): 1 bulan setelah kegiatan selesai,
     * paling lambat 10 Januari tahun berikutnya (tahun anggaran + 1).
     * Penerapan batas 10 Januari dapat dimatikan (lpj.cap_enabled) bila Bagian Hukum menafsirkan lain.
     */
    public function lpjDueDate(CarbonInterface $activityEnd, int $fiscalYear): Carbon
    {
        $due = Carbon::instance($activityEnd)->startOfDay()
            ->addMonthsNoOverflow(max(0, Setting::int('lpj.months_after_activity')));

        if (Setting::bool('lpj.cap_enabled')) {
            $cap = Carbon::create($fiscalYear + 1, Setting::int('lpj.cap_month'), Setting::int('lpj.cap_day'))->startOfDay();
            if ($due->gt($cap)) {
                return $cap;
            }
        }

        return $due;
    }

    /** Sisa dana hibah uang = dana tersalur - realisasi sah (tidak pernah negatif). */
    public function remainingFunds(int $disbursed, int $realized): int
    {
        return max(0, $disbursed - $realized);
    }

    /** Jumlah RAB dari baris-baris (volume x harga satuan). */
    public function lineSubtotal(float $qty, int $unitPrice): int
    {
        return (int) round($qty * $unitPrice);
    }

    public function percent(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    /** Keterlambatan LPJ dalam hari (0 bila belum lewat / sudah dilaporkan tepat waktu). */
    public function daysLate(GrantApplication $app, ?CarbonInterface $now = null): int
    {
        if (! $app->lpj_due_date) {
            return 0;
        }
        $now ??= now();

        return $now->startOfDay()->gt($app->lpj_due_date) ? (int) $app->lpj_due_date->diffInDays($now->startOfDay()) : 0;
    }
}
