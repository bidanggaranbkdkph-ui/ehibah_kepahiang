<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|string|max:255', 'password' => 'required|string']);
        $id = mb_strtolower(trim($data['email']));
        // Boleh masuk dengan email lengkap, atau nama pengguna singkat (bagian sebelum '@') bila hanya cocok dengan satu akun.
        $user = User::query()->where('email', $id)->first();
        if (! $user && ! str_contains($id, '@')) {
            $matches = User::query()->where('email', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $id).'@%')->limit(2)->get();
            $user = $matches->count() === 1 ? $matches->first() : null;
        }
        $generic = 'Email atau kata sandi salah.';

        if ($user && $user->isLocked()) {
            $this->audit->access('LOGIN_BLOCKED_LOCKED', $user);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi setelah '.$user->locked_until->format('H:i').'.']);
        }

        // hash dummy agar waktu respons tidak membocorkan keberadaan akun
        $ok = Hash::check($data['password'], $user?->password ?? Hash::make('tidak-ada-akun')) && $user !== null;

        if (! $user || ! $ok) {
            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= Setting::int('security.max_login_attempts')) {
                    $user->update(['locked_until' => now()->addMinutes(Setting::int('security.lockout_minutes')), 'failed_login_attempts' => 0]);
                    $this->audit->access('ACCOUNT_LOCKED', $user);
                }
            }
            $this->audit->access('LOGIN_FAILED', $user, ['email' => $data['email']]);

            return back()->withInput($request->only('email'))->withErrors(['email' => $generic]);
        }

        if ($user->account_status === 'PENDING') {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Registrasi Anda masih menunggu validasi admin.']);
        }
        if (! $user->is_active || $user->account_status !== 'ACTIVE') {
            $this->audit->access('LOGIN_BLOCKED_INACTIVE', $user);

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Akun tidak aktif. Hubungi admin layanan.']);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->forget('mfa_ok');
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $request->ip()]);
        $this->audit->access('LOGIN_SUCCESS', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $this->audit->access('LOGOUT', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
