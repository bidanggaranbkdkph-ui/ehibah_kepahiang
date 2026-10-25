<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Format tampilan bahasa Indonesia. */
class Fmt
{
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public static function rupiah(int|float|null $n, bool $prefix = true): string
    {
        $s = number_format((float) ($n ?? 0), 0, ',', '.');

        return $prefix ? 'Rp '.$s : $s;
    }

    /** Ringkas: 12,5 M / 850 jt. */
    public static function rupiahShort(int|float|null $n): string
    {
        $n = (float) ($n ?? 0);
        if ($n >= 1_000_000_000_000) {
            return 'Rp '.number_format($n / 1_000_000_000_000, 1, ',', '.').' T';
        }
        if ($n >= 1_000_000_000) {
            return 'Rp '.number_format($n / 1_000_000_000, 1, ',', '.').' M';
        }
        if ($n >= 1_000_000) {
            return 'Rp '.number_format($n / 1_000_000, 0, ',', '.').' jt';
        }

        return self::rupiah($n);
    }

    public static function date(CarbonInterface|string|null $d, bool $short = false): string
    {
        if (! $d) {
            return '-';
        }
        $d = $d instanceof CarbonInterface ? $d : Carbon::parse($d);
        $m = self::BULAN[(int) $d->format('n')];

        return $d->format('j').' '.($short ? mb_substr($m, 0, 3) : $m).' '.$d->format('Y');
    }

    public static function datetime(CarbonInterface|string|null $d): string
    {
        if (! $d) {
            return '-';
        }
        $d = $d instanceof CarbonInterface ? $d : Carbon::parse($d);

        return self::date($d, true).' '.$d->format('H:i');
    }

    /** Terbilang sederhana untuk dokumen resmi (NPHD). */
    public static function terbilang(int $n): string
    {
        $h = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($n < 12) {
            return $h[$n];
        }
        if ($n < 20) {
            return self::terbilang($n - 10).' belas';
        }
        if ($n < 100) {
            return self::terbilang(intdiv($n, 10)).' puluh'.($n % 10 ? ' '.self::terbilang($n % 10) : '');
        }
        if ($n < 200) {
            return 'seratus'.($n - 100 ? ' '.self::terbilang($n - 100) : '');
        }
        if ($n < 1000) {
            return self::terbilang(intdiv($n, 100)).' ratus'.($n % 100 ? ' '.self::terbilang($n % 100) : '');
        }
        if ($n < 2000) {
            return 'seribu'.($n - 1000 ? ' '.self::terbilang($n - 1000) : '');
        }
        if ($n < 1_000_000) {
            return self::terbilang(intdiv($n, 1000)).' ribu'.($n % 1000 ? ' '.self::terbilang($n % 1000) : '');
        }
        if ($n < 1_000_000_000) {
            return self::terbilang(intdiv($n, 1_000_000)).' juta'.($n % 1_000_000 ? ' '.self::terbilang($n % 1_000_000) : '');
        }
        if ($n < 1_000_000_000_000) {
            return self::terbilang(intdiv($n, 1_000_000_000)).' miliar'.($n % 1_000_000_000 ? ' '.self::terbilang($n % 1_000_000_000) : '');
        }

        return self::terbilang(intdiv($n, 1_000_000_000_000)).' triliun'.($n % 1_000_000_000_000 ? ' '.self::terbilang($n % 1_000_000_000_000) : '');
    }
}
