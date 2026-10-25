<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RecipientCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** SOP 1: registrasi calon pemohon. Akun baru berstatus PENDING sampai divalidasi admin layanan. */
class RegisterController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function show()
    {
        return view('auth.register', ['categories' => RecipientCategory::where('is_active', true)->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'org_name' => 'required|string|max:200',
            'recipient_category_id' => 'required|exists:recipient_categories,id',
            'address' => 'required|string|max:500',
            'contact' => 'nullable|string|max:100',
            'npwp' => 'nullable|string|max:30',
            'legal_doc_no' => 'nullable|string|max:120',
            'legal_doc_date' => 'nullable|date',
            'chair_name' => 'required|string|max:150',
            'secretary_name' => 'nullable|string|max:150',
            'name' => 'required|string|max:150',
            'nip_nik' => 'required|string|max:30|unique:users,nip_nik',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'required|string|max:30',
            'position' => 'nullable|string|max:150',
            'password' => ['required', 'confirmed', Password::min(Setting::int('security.password_min'))->mixedCase()->numbers()],
            'agree' => 'accepted',
        ], [], [
            'org_name' => 'Nama lembaga', 'recipient_category_id' => 'Kategori penerima', 'chair_name' => 'Nama ketua',
            'nip_nik' => 'NIK', 'name' => 'Nama pengurus', 'password' => 'Kata sandi', 'agree' => 'Pernyataan kebenaran data',
        ]);

        DB::transaction(function () use ($d) {
            $org = Organization::create([
                'code' => 'L-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                'name' => $d['org_name'], 'type' => 'LEMBAGA', 'recipient_category_id' => $d['recipient_category_id'],
                'address' => $d['address'], 'contact' => $d['contact'] ?? null, 'npwp' => $d['npwp'] ?? null,
                'legal_doc_no' => $d['legal_doc_no'] ?? null, 'legal_doc_date' => $d['legal_doc_date'] ?? null,
                'chair_name' => $d['chair_name'], 'secretary_name' => $d['secretary_name'] ?? null, 'is_active' => true,
            ]);
            $user = User::create([
                'organization_id' => $org->id, 'name' => $d['name'], 'nip_nik' => $d['nip_nik'], 'email' => mb_strtolower($d['email']),
                'phone' => $d['phone'], 'position' => $d['position'] ?? null, 'password' => $d['password'],
                'account_status' => 'PENDING', 'is_active' => false, 'password_changed_at' => now(),
            ]);
            $user->roles()->attach(Role::where('code', 'PEMOHON')->value('id'));
            $this->audit->record('REGISTERED', $user, null, ['email' => $user->email, 'org' => $org->name], 'AKSES', null, $user);
        });

        return redirect()->route('login')->with('success', 'Registrasi diterima. Admin layanan akan memvalidasi akun Anda; pemberitahuan dikirim ke email.');
    }
}
