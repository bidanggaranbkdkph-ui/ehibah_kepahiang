<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang dinonaktifkan/dikunci di tengah sesi langsung dikeluarkan. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && (! $u->is_active || $u->account_status !== 'ACTIVE' || $u->isLocked())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun tidak aktif atau terkunci. Hubungi admin.']);
        }

        return $next($request);
    }
}
