<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->mfa_enabled_at || $request->session()->get('mfa_ok')) {
            return redirect()->route('dashboard');
        }

        return view('auth.mfa-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $u = $request->user();
        if (! $u->mfa_secret || ! Totp::verify($u->mfa_secret, $request->input('code'))) {
            $this->audit->access('MFA_FAILED', $u);
            throw ValidationException::withMessages(['code' => 'Kode verifikasi salah atau kedaluwarsa.']);
        }
        $request->session()->put('mfa_ok', true);
        $this->audit->access('MFA_PASSED', $u);

        return redirect()->intended(route('dashboard'));
    }

    public function setup(Request $request)
    {
        $u = $request->user();
        if ($u->mfa_enabled_at) {
            return redirect()->route('profile.edit');
        }
        $secret = $request->session()->get('mfa_pending') ?? tap(Totp::generateSecret(), fn ($s) => $request->session()->put('mfa_pending', $s));

        return view('auth.mfa-setup', ['secret' => $secret, 'uri' => Totp::uri($secret, $u->email)]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $secret = $request->session()->get('mfa_pending');
        if (! $secret || ! Totp::verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'Kode tidak cocok. Pindai ulang rahasia di aplikasi autentikator lalu coba lagi.']);
        }
        $request->user()->update(['mfa_secret' => $secret, 'mfa_enabled_at' => now()]);
        $request->session()->forget('mfa_pending');
        $request->session()->put('mfa_ok', true);
        $this->audit->access('MFA_ENABLED', $request->user());

        return redirect()->route('profile.edit')->with('success', 'Verifikasi dua langkah aktif.');
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $u = $request->user();
        if (! Hash::check($request->input('password'), $u->password)) {
            throw ValidationException::withMessages(['password' => 'Kata sandi salah.']);
        }
        if ($u->requiresMfa()) {
            return back()->withErrors(['password' => 'Peran Anda wajib MFA; tidak dapat dinonaktifkan.']);
        }
        $u->update(['mfa_secret' => null, 'mfa_enabled_at' => null]);
        $this->audit->access('MFA_DISABLED', $u);

        return back()->with('success', 'MFA dinonaktifkan.');
    }
}
