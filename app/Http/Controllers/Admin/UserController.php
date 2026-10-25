<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $users = User::with(['organization', 'roles'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', ['%'.mb_strtolower($request->q).'%'])->orWhereRaw('lower(email) like ?', ['%'.mb_strtolower($request->q).'%'])))
            ->when($request->filled('status'), fn ($q) => $q->where('account_status', $request->status))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('code', $request->role)))
            ->orderByRaw("case account_status when 'PENDING' then 0 else 1 end")->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.users', [
            'users' => $users, 'roles' => Role::orderBy('id')->get(), 'orgs' => Organization::orderBy('type')->orderBy('name')->get(),
        ]);
    }

    /** Admin membuat akun petugas (OPD, Tim Verifikasi, TAPD, dst.). Kata sandi sementara wajib diganti. */
    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => 'required|string|max:150', 'email' => 'required|email|unique:users,email', 'nip_nik' => 'nullable|string|max:30|unique:users,nip_nik',
            'phone' => 'nullable|string|max:30', 'position' => 'nullable|string|max:150', 'organization_id' => 'required|exists:organizations,id',
            'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,code',
        ]);
        $this->guardRoles($d['roles']);
        $temp = Str::password(14);
        $user = User::create([
            'name' => $d['name'], 'email' => mb_strtolower($d['email']), 'nip_nik' => $d['nip_nik'] ?? null, 'phone' => $d['phone'] ?? null,
            'position' => $d['position'] ?? null, 'organization_id' => $d['organization_id'], 'password' => $temp,
            'account_status' => 'ACTIVE', 'is_active' => true,
        ]);
        $user->roles()->sync(Role::whereIn('code', $d['roles'])->pluck('id'));
        $this->audit->system('USER_CREATED', $user, null, ['email' => $user->email, 'roles' => $d['roles']]);

        return back()->with('success', "Akun dibuat. Kata sandi sementara (sampaikan lewat kanal aman, tampil sekali): $temp");
    }

    public function validateAccount(Request $request, User $user)
    {
        $request->validate(['decision' => 'required|in:approve,reject', 'note' => 'nullable|string|max:500']);
        if ($user->account_status !== 'PENDING') {
            return back()->with('error', 'Akun ini tidak sedang menunggu validasi.');
        }
        $ok = $request->input('decision') === 'approve';
        $user->update(['account_status' => $ok ? 'ACTIVE' : 'REJECTED', 'is_active' => $ok]);
        $this->audit->system($ok ? 'USER_VALIDATED' : 'USER_REJECTED', $user, ['status' => 'PENDING'], ['status' => $user->account_status, 'note' => $request->input('note')]);

        return back()->with('success', $ok ? 'Akun divalidasi dan diaktifkan.' : 'Registrasi ditolak.');
    }

    public function update(Request $request, User $user)
    {
        $d = $request->validate([
            'account_status' => ['required', Rule::in(['ACTIVE', 'SUSPENDED'])], 'roles' => 'required|array|min:1', 'roles.*' => 'exists:roles,code',
            'organization_id' => 'required|exists:organizations,id', 'position' => 'nullable|string|max:150',
        ]);
        $this->guardRoles($d['roles']);
        if ($user->id === $request->user()->id && $d['account_status'] !== 'ACTIVE') {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $old = ['status' => $user->account_status, 'roles' => $user->roleCodes(), 'org' => $user->organization_id];
        $user->update(['account_status' => $d['account_status'], 'is_active' => $d['account_status'] === 'ACTIVE', 'organization_id' => $d['organization_id'], 'position' => $d['position'] ?? $user->position]);
        $user->roles()->sync(Role::whereIn('code', $d['roles'])->pluck('id'));
        $user->forgetRoleCache();
        $this->audit->system('USER_UPDATED', $user, $old, ['status' => $d['account_status'], 'roles' => $d['roles'], 'org' => $d['organization_id']]);

        return back()->with('success', 'Pengguna diperbarui.');
    }

    public function reset(Request $request, User $user)
    {
        $d = $request->validate(['what' => 'required|in:password,mfa,unlock']);
        $msg = '';
        if ($d['what'] === 'password') {
            $temp = Str::password(14);
            $user->update(['password' => $temp, 'password_changed_at' => null, 'failed_login_attempts' => 0, 'locked_until' => null]);
            $msg = "Kata sandi sementara: $temp (tampil sekali).";
        } elseif ($d['what'] === 'mfa') {
            $user->update(['mfa_secret' => null, 'mfa_enabled_at' => null]);
            $msg = 'MFA pengguna direset; pengguna harus mendaftar ulang.';
        } else {
            $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
            $msg = 'Kunci akun dibuka.';
        }
        $this->audit->system('USER_RESET_'.strtoupper($d['what']), $user);

        return back()->with('success', $msg);
    }

    /** Pemisahan tugas: peran ADMIN tidak boleh digabung dengan peran proses hibah. */
    private function guardRoles(array $roles): void
    {
        if (in_array('ADMIN', $roles, true) && count($roles) > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['roles' => 'Admin Sistem tidak boleh merangkap peran proses hibah (pemisahan tugas).']);
        }
        if (in_array('PEMOHON', $roles, true) && count($roles) > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['roles' => 'Pemohon tidak boleh merangkap peran petugas (benturan kepentingan).']);
        }
    }
}
