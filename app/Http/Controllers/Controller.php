<?php

namespace App\Http\Controllers;

use App\Models\GrantApplication;
use App\Support\Permission;
use Illuminate\Auth\Access\AuthorizationException;

abstract class Controller
{
    /** Pastikan pengguna boleh melihat usulan (row-level security). */
    protected function authorizeView(GrantApplication $app): void
    {
        $user = auth()->user();
        if (! GrantApplication::query()->visibleTo($user)->whereKey($app->id)->exists()) {
            throw new AuthorizationException('Usulan ini di luar cakupan akses Anda.');
        }
    }

    protected function requirePerm(string $feature, string $level = 'V'): void
    {
        if (! Permission::allows(auth()->user(), $feature, $level)) {
            throw new AuthorizationException("Tidak berwenang untuk fitur '$feature'.");
        }
    }
}
