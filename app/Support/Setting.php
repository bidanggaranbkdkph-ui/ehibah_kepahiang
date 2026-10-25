<?php

namespace App\Support;

use App\Models\Setting as SettingModel;
use Illuminate\Support\Facades\Schema;

/**
 * Akses konfigurasi bisnis: tabel `settings` menimpa config('ehibah.defaults').
 */
class Setting
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        // kunci bawaan memuat titik, jadi dibaca dari array (bukan notasi titik config())
        return $all[$key] ?? (config('ehibah.defaults')[$key] ?? $default);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(self::get($key), FILTER_VALIDATE_BOOLEAN);
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            if (Schema::hasTable('settings')) {
                foreach (SettingModel::all() as $row) {
                    self::$cache[$row->key] = json_decode($row->value, true);
                }
            }
        }

        return self::$cache;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
