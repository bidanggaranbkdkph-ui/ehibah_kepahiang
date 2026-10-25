<?php

namespace App\Support;

use App\Models\User;

/**
 * Pembaca matriks hak akses (config/ehibah.php -> permissions).
 * Tingkat: F (penuh) > P (proses) > V (lihat) > N (tidak ada).
 */
class Permission
{
    public const RANK = ['N' => 0, 'V' => 1, 'P' => 2, 'F' => 3];

    /** Fitur/peran yang dikunci agar Admin tidak mengunci dirinya sendiri dari sistem. */
    public const LOCKED = [
        ['profil', null],            // akun sendiri: semua peran tetap Kelola penuh
        ['kelola_pengguna', 'ADMIN'],
        ['konfigurasi', 'ADMIN'],
    ];

    public const LEVELS = [
        'F' => 'Kelola penuh (buat, ubah, setujui)',
        'P' => 'Proses / verifikasi',
        'V' => 'Lihat saja',
        'N' => 'Tidak ada akses',
    ];

    public static function isLocked(string $feature, string $role): bool
    {
        foreach (self::LOCKED as [$f, $r]) {
            if ($f === $feature && ($r === null || $r === $role)) {
                return true;
            }
        }

        return false;
    }

    /** Bawaan dari config (tanpa penimpaan yang disimpan Admin). */
    public static function defaultLevel(string $role, string $feature): string
    {
        return config("ehibah.permissions.$feature.$role", 'N');
    }

    public static function levelForRole(string $role, string $feature): string
    {
        if (self::isLocked($feature, $role)) {
            return self::defaultLevel($role, $feature);
        }
        $over = Setting::get('permissions.overrides', [])[$feature][$role] ?? null;

        return isset(self::RANK[$over]) ? $over : self::defaultLevel($role, $feature);
    }

    public static function levelForUser(User $user, string $feature): string
    {
        $best = 'N';
        foreach ($user->roleCodes() as $role) {
            $lvl = self::levelForRole($role, $feature);
            if (self::RANK[$lvl] > self::RANK[$best]) {
                $best = $lvl;
            }
        }

        return $best;
    }

    public static function allows(User $user, string $feature, string $min = 'V'): bool
    {
        return self::RANK[self::levelForUser($user, $feature)] >= self::RANK[$min];
    }

    /** Seluruh matriks sebagai [grup => [[feature,label,[role=>level]]]] untuk halaman matriks. */
    public static function matrix(): array
    {
        $out = [];
        foreach (config('ehibah.features') as $key => [$label, $group]) {
            $row = [];
            foreach (array_keys(config('ehibah.roles')) as $role) {
                $row[$role] = self::levelForRole($role, $key);
            }
            $out[$group][] = ['feature' => $key, 'label' => $label, 'levels' => $row];
        }

        return $out;
    }
}
