<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()->load('organization')]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'position' => 'nullable|string|max:150',
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($u->id)],
        ]);
        $old = $u->only(array_keys($d));
        $u->update($d);
        $this->audit->access('PROFILE_UPDATED', $u, ['old' => $old, 'new' => $d]);

        return back()->with('success', 'Profil diperbarui.');
    }

    /** Pemohon melengkapi data lembaga (rekening, ketua/sekretaris) yang diwajibkan guard pengajuan. */
    public function updateOrganization(Request $request)
    {
        $u = $request->user();
        $this->requirePerm('pengajuan', 'F');
        $org = $u->organization;
        $d = $request->validate([
            'address' => 'required|string|max:500', 'contact' => 'nullable|string|max:100', 'npwp' => 'nullable|string|max:30',
            'legal_doc_no' => 'nullable|string|max:120', 'legal_doc_date' => 'nullable|date',
            'chair_name' => 'required|string|max:150', 'secretary_name' => 'nullable|string|max:150',
            'bank_name' => 'nullable|string|max:100', 'bank_account_no' => 'nullable|string|max:40', 'bank_account_name' => 'nullable|string|max:150',
        ]);
        $old = $org->only(array_keys($d));
        $org->update($d);
        $this->audit->record('ORG_UPDATED', $org, $old, $d, 'AKSES', null, $u);

        return back()->with('success', 'Data lembaga diperbarui.');
    }

    public function password(Request $request)
    {
        $d = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(Setting::int('security.password_min'))->mixedCase()->numbers()],
        ], [], ['current_password' => 'Kata sandi saat ini', 'password' => 'Kata sandi baru']);

        $u = $request->user();
        if (! Hash::check($d['current_password'], $u->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }
        $u->update(['password' => $d['password'], 'password_changed_at' => now()]);
        $this->audit->access('PASSWORD_CHANGED', $u);

        return back()->with('success', 'Kata sandi diganti.');
    }
}
