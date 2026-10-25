<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Peran berhak istimewa wajib lolos MFA (TOTP) bila kebijakan security.mfa_enforced aktif. */
class EnsureMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return $next($request);
        }
        if ($u->mfa_enabled_at && ! $request->session()->get('mfa_ok')) {
            return redirect()->route('mfa.challenge');
        }
        if (! $u->mfa_enabled_at && $u->requiresMfa() && ! $request->routeIs('mfa.*', 'logout')) {
            return redirect()->route('mfa.setup')->with('warning', 'Peran Anda wajib mengaktifkan verifikasi dua langkah (MFA).');
        }

        return $next($request);
    }
}
