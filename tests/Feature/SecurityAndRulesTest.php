<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\AuditLog;
use App\Models\DataCorrection;
use App\Models\User;
use App\Services\CorrectionService;
use App\Services\DocumentService;
use App\Services\WorkflowService;
use App\Support\Permission;
use App\Support\Totp;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\UsesScenario;
use Tests\TestCase;

class SecurityAndRulesTest extends TestCase
{
    use UsesScenario;

    // ------------------------------------------------------------ autentikasi
    public function test_login_berhasil_dan_tercatat(): void
    {
        $this->post('/masuk', ['email' => 'pemohon@ehibah.test', 'password' => ScenarioBuilderPassword::get()])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_SUCCESS', 'category' => 'AKSES']);
    }

    public function test_akun_terkunci_setelah_lima_kali_gagal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['email' => 'opd@ehibah.test', 'password' => 'salah-salah'])->assertSessionHasErrors('email');
        }
        $this->assertTrue($this->s->u['opd']->fresh()->isLocked());
        // sandi benar pun ditolak saat terkunci
        $this->post('/masuk', ['email' => 'opd@ehibah.test', 'password' => ScenarioBuilderPassword::get()])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'ACCOUNT_LOCKED']);
    }

    public function test_pesan_galat_login_tidak_membocorkan_keberadaan_akun(): void
    {
        // akun tidak ada vs sandi salah: pesan identik (tidak membocorkan keberadaan akun)
        $msg = 'Email atau kata sandi salah.';
        $this->post('/masuk', ['email' => 'tidakada@ehibah.test', 'password' => 'x'])->assertSessionHasErrors(['email' => $msg]);
        $this->post('/masuk', ['email' => 'opd@ehibah.test', 'password' => 'x'])->assertSessionHasErrors(['email' => $msg]);
    }

    public function test_registrasi_pemohon_menunggu_validasi_admin(): void
    {
        $cat = \App\Models\RecipientCategory::where('code', 'ORMAS')->value('id');
        $payload = [
            'org_name' => 'Karang Taruna Mekar', 'recipient_category_id' => $cat, 'address' => 'Desa Mekar', 'chair_name' => 'Rudi',
            'name' => 'Rudi Hartono', 'nip_nik' => '1707012345678901', 'email' => 'rudi@mekar.test', 'phone' => '0812', 'password' => 'Rahasia12345', 'password_confirmation' => 'Rahasia12345', 'agree' => 1,
        ];
        $this->post('/daftar', $payload)->assertRedirect('/masuk');
        $u = User::where('email', 'rudi@mekar.test')->first();
        $this->assertSame('PENDING', $u->account_status);
        $this->assertFalse((bool) $u->is_active);
        $this->assertTrue($u->hasRole('PEMOHON'));

        // belum bisa masuk
        $this->post('/masuk', ['email' => 'rudi@mekar.test', 'password' => 'Rahasia12345'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // admin memvalidasi
        $this->actingAs($this->s->u['admin'])->post("/admin/pengguna/{$u->id}/validasi", ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('ACTIVE', $u->fresh()->account_status);
        auth()->logout();
        $this->post('/masuk', ['email' => 'rudi@mekar.test', 'password' => 'Rahasia12345'])->assertRedirect('/dashboard');
    }

    public function test_kata_sandi_lemah_ditolak_saat_registrasi(): void
    {
        $cat = \App\Models\RecipientCategory::where('code', 'ORMAS')->value('id');
        $r = $this->post('/daftar', [
            'org_name' => 'X', 'recipient_category_id' => $cat, 'address' => 'a', 'chair_name' => 'b', 'name' => 'c', 'nip_nik' => '1', 'email' => 'x@x.test',
            'phone' => '1', 'password' => 'pendek', 'password_confirmation' => 'pendek', 'agree' => 1,
        ]);
        $r->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'x@x.test']);
    }

    public function test_totp_sesuai_rfc6238(): void
    {
        // vektor uji RFC 6238 (SHA-1, secret "12345678901234567890" = base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ), 8 digit -> ambil 6 digit terakhir
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertTrue(Totp::verify($secret, Totp::code($secret), 1));
        $this->assertFalse(Totp::verify($secret, '000000', 0, 59));
        $this->assertFalse(Totp::verify($secret, 'abcdef'));
    }

    public function test_mfa_wajib_untuk_peran_istimewa_bila_kebijakan_aktif(): void
    {
        \App\Models\Setting::create(['key' => 'security.mfa_enforced', 'value' => 'true']);
        \App\Support\Setting::forget();
        $this->actingAs($this->s->u['bpkad'])->get('/dashboard')->assertRedirect(route('mfa.setup'));
        $this->actingAs($this->s->u['pemohon'])->get('/dashboard')->assertOk(); // peran biasa tidak wajib

        $secret = Totp::generateSecret();
        $this->s->u['bpkad']->update(['mfa_secret' => $secret, 'mfa_enabled_at' => now()]);
        $this->actingAs($this->s->u['bpkad']->fresh())->get('/dashboard')->assertRedirect(route('mfa.challenge'));
        $this->post('/mfa/verifikasi', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/mfa/verifikasi', ['code' => Totp::code($secret)])->assertRedirect();
        $this->get('/dashboard')->assertOk();
    }

    public function test_header_keamanan(): void
    {
        $r = $this->get('/');
        $r->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'self'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('cdn', $r->getContent()); // tanpa aset pihak ketiga
    }

    // ------------------------------------------------------------ unggah berkas
    public function test_berkas_palsu_atau_terlalu_besar_atau_tipe_terlarang_ditolak(): void
    {
        $app = $this->s->newApplication();
        $svc = app(DocumentService::class);
        $u = $this->s->u['pemohon'];

        // exe disamarkan sebagai pdf
        $msg = $this->failureMessages(fn () => $svc->store($app, $u, 'PENGAJUAN', 'PROPOSAL', File::createWithContent('proposal.pdf', "MZ\x90\x00 bukan pdf")));
        $this->assertStringContainsString('tidak sesuai dengan ekstensinya', $msg);
        // ekstensi terlarang
        $msg = $this->failureMessages(fn () => $svc->store($app, $u, 'PENGAJUAN', 'PROPOSAL', File::createWithContent('shell.php', '<?php echo 1;')));
        $this->assertStringContainsString('tidak diizinkan', $msg);
        // terlalu besar (batas 5 MB)
        $big = File::createWithContent('besar.pdf', "%PDF-1.4\n".str_repeat('A', 5 * 1024 * 1024 + 10));
        $msg = $this->failureMessages(fn () => $svc->store($app, $u, 'PENGAJUAN', 'PROPOSAL', $big));
        $this->assertStringContainsString('melebihi 5 MB', $msg);
        // jenis dokumen tidak dikenal
        $msg = $this->failureMessages(fn () => $svc->store($app, $u, 'PENGAJUAN', 'BUKAN_DOKUMEN', File::createWithContent('a.pdf', "%PDF-1.4\n%%EOF")));
        $this->assertStringContainsString('tidak dikenal', $msg);
        $this->assertSame(0, $app->documents()->count());
    }

    public function test_berkas_tersimpan_privat_dengan_hash_dan_tidak_dapat_diakses_publik(): void
    {
        $app = $this->s->newApplication();
        $doc = app(DocumentService::class)->store($app, $this->s->u['pemohon'], 'PENGAJUAN', 'PROPOSAL', File::createWithContent('p.pdf', "%PDF-1.4\nisi\n%%EOF"));
        $this->assertSame(64, strlen($doc->file_hash));
        $this->assertStringStartsWith("applications/{$app->id}/", $doc->file_path);
        $this->assertStringNotContainsString('p.pdf', $doc->file_path); // nama asli tidak dipakai di path
        // tidak ada symlink/URL publik ke berkas
        $this->get('/storage/'.$doc->file_path)->assertNotFound();
        // unduhan tanpa login diarahkan ke login
        auth()->logout();
        $this->get(route('documents.download', $doc))->assertRedirect('/masuk');
    }

    // ------------------------------------------------------------ matriks & konsistensi
    public function test_setiap_aksi_workflow_selaras_dengan_matriks_hak_akses(): void
    {
        $feature = [
            'submit' => 'pengajuan', 'applicant_cancel' => 'pengajuan', 'start_admin' => 'verifikasi_adm', 'admin_pass' => 'verifikasi_adm', 'admin_return' => 'verifikasi_adm',
            'admin_reject' => 'verifikasi_adm', 'tech_return' => 'verifikasi_teknis', 'tech_reject' => 'verifikasi_teknis', 'recommend' => 'rekomendasi',
            'tapd_start' => 'penetapan', 'tapd_reject' => 'penetapan', 'tapd_cancel' => 'penetapan', 'approve' => 'penetapan', 'bupati_reject' => 'penetapan',
            'confirm_budget' => 'anggaran', 'budget_cancel' => 'anggaran', 'sign_nphd' => 'nphd', 'mark_disbursed' => 'penyaluran', 'submit_lpj' => 'lpj',
            'lpj_return' => 'lpj', 'close' => 'lpj',
        ];
        foreach (WorkflowService::TRANSITIONS as $action => $def) {
            $this->assertArrayHasKey($action, $feature, "Aksi $action belum dipetakan ke fitur");
            foreach ($def['roles'] as $role) {
                $this->assertContains(Permission::levelForRole($role, $feature[$action]), ['P', 'F'], "Peran $role pada aksi $action harus ≥ Proses di fitur {$feature[$action]}");
            }
        }
    }

    public function test_matriks_sesuai_blueprint(): void
    {
        // contoh dari Blueprint Bab 6
        $this->assertSame('N', Permission::levelForRole('PEMOHON', 'anggaran'));      // pemohon tak punya akses anggaran
        $this->assertSame('F', Permission::levelForRole('PEMOHON', 'pengajuan'));
        $this->assertSame('P', Permission::levelForRole('VERIFIKATOR', 'verifikasi_teknis'));
        $this->assertSame('F', Permission::levelForRole('BUPATI', 'penetapan'));
        $this->assertSame('F', Permission::levelForRole('BPKAD', 'penyaluran'));
        $this->assertSame('F', Permission::levelForRole('INSPEKTORAT', 'audit_trail'));
        $this->assertSame('N', Permission::levelForRole('ADMIN', 'lpj'));              // Admin tak menyentuh data permohonan
        $this->assertSame('N', Permission::levelForRole('ADMIN', 'penetapan'));
        $this->assertSame('F', Permission::levelForRole('ADMIN', 'kelola_pengguna'));
    }

    // ------------------------------------------------------------ konkurensi
    public function test_perubahan_bersamaan_ditolak_dengan_lock_version(): void
    {
        $app = $this->appAt(Status::SUBMITTED);
        $stale = $app->lock_version;
        $this->s->act($app, 'start_admin', 'opd'); // pengguna lain sudah memproses
        $msg = $this->failureMessages(fn () => app(WorkflowService::class)->perform($app->fresh(), 'applicant_cancel', $this->s->u['pemohon'], ['lock_version' => $stale, 'note' => 'batal']));
        $this->assertStringContainsString('diubah pengguna lain', $msg);
    }

    public function test_aksi_pada_status_salah_ditolak(): void
    {
        $app = $this->appAt(Status::DRAFT);
        foreach (['approve', 'close', 'confirm_budget', 'mark_disbursed'] as $a) {
            try {
                app(WorkflowService::class)->perform($app, $a, $this->s->u['bupati'], []);
                $this->fail("$a seharusnya ditolak");
            } catch (ValidationException|\Illuminate\Auth\Access\AuthorizationException) {
                $this->assertSame(Status::DRAFT, $app->fresh()->status);
            }
        }
    }

    // ------------------------------------------------------------ penolakan & koreksi data
    public function test_penolakan_wajib_beralasan_membebaskan_pagu_dan_notifikasi(): void
    {
        $app = $this->appAt(Status::TAPD_REVIEW);
        app(\App\Services\ProcessService::class)->recordTapdReview($app->fresh(), $this->s->u['tapd'], (int) $app->amount_recommended, null);
        $ceiling = \App\Models\BudgetCeiling::where('opd_id', $app->opd_id)->first();
        $this->assertSame(100_000_000, $ceiling->used());

        $msg = $this->failureMessages(fn () => $this->s->act($app, 'bupati_reject', 'bupati', []));
        $this->assertStringContainsString('wajib diisi', $msg);

        $rejected = $this->s->act($app, 'bupati_reject', 'bupati', ['note' => 'Tidak sesuai prioritas anggaran']);
        $this->assertSame(Status::REJECTED, $rejected->status);
        $this->assertSame('Tidak sesuai prioritas anggaran', $rejected->rejection_reason);
        $this->assertSame(0, $ceiling->fresh()->used()); // dana kembali ke pagu
        $this->assertSame('RELEASED', $app->fresh()->allocation->status);
        $this->assertTrue($this->s->u['pemohon']->notifications->contains(fn ($n) => ($n->data['title'] ?? '') === 'Status usulan: Ditolak'));
        // status terminal: tidak ada aksi lanjutan
        $this->assertSame([], app(WorkflowService::class)->availableActions($rejected, $this->s->u['pemohon']));
    }

    public function test_koreksi_data_butuh_alasan_penyetuju_lain_dan_tercatat(): void
    {
        $app = $this->appAt(Status::TECH_CHECK);
        $svc = app(CorrectionService::class);
        $old = $app->title;

        // bidang uang tidak boleh dikoreksi lewat jalur ini
        $msg = $this->failureMessages(fn () => $svc->request($app, $this->s->u['opd'], 'application', $app->id, 'amount_requested', '999', 'koreksi nominal uang'));
        $this->assertStringContainsString('tidak dapat dikoreksi', $msg);
        // alasan wajib
        $msg = $this->failureMessages(fn () => $svc->request($app, $this->s->u['opd'], 'application', $app->id, 'title', 'Judul Baru', 'x'));
        $this->assertStringContainsString('Alasan', $msg);

        $c = $svc->request($app, $this->s->u['opd'], 'application', $app->id, 'title', 'Judul Setelah Koreksi', 'Salah ketik pada judul usulan');
        $this->assertSame($old, $c->old_value);
        $this->assertSame($old, $app->fresh()->title); // belum berubah sebelum disetujui

        // pengaju tidak boleh menyetujui sendiri (4-mata); pemohon & pihak tak berwenang pun tidak
        foreach (['opd', 'pemohon', 'bupati'] as $who) {
            try {
                $svc->decide($c, $this->s->u[$who], true, null);
                $this->fail("$who tidak boleh menyetujui");
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                $this->assertSame('DIAJUKAN', $c->fresh()->status);
            }
        }
        $svc->decide($c, $this->s->u['verifikator'], true, 'Disetujui');
        $this->assertSame('Judul Setelah Koreksi', $app->fresh()->title);
        $this->assertSame('DISETUJUI', $c->fresh()->status);

        $log = AuditLog::where('action', 'CORRECTION_APPLIED')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($old, $log->old_values);
        $this->assertStringContainsString('Judul Setelah Koreksi', $log->new_values);
        $this->assertGreaterThan($app->lock_version, $app->fresh()->lock_version);
    }

    public function test_koreksi_ditolak_tidak_mengubah_data_dan_nilai_lama_dijaga(): void
    {
        $app = $this->appAt(Status::TECH_CHECK);
        $svc = app(CorrectionService::class);
        $c = $svc->request($app, $this->s->u['opd'], 'application', $app->id, 'location', 'Lokasi Lain', 'Alamat lokasi keliru pada usulan');
        $this->assertStringContainsString('wajib', $this->failureMessages(fn () => $svc->decide($c, $this->s->u['verifikator'], false, '')));
        $svc->decide($c, $this->s->u['verifikator'], false, 'Tidak didukung bukti');
        $this->assertSame('DITOLAK', $c->fresh()->status);
        $this->assertNotSame('Lokasi Lain', $app->fresh()->location);

        // data berubah setelah pengajuan => penerapan ditolak (hindari menimpa perubahan lain)
        $c2 = $svc->request($app->fresh(), $this->s->u['opd'], 'application', $app->id, 'target', 'Sasaran baru', 'Perbaikan sasaran sesuai proposal');
        $app->fresh()->update(['target' => 'Diubah pihak lain']);
        $this->assertStringContainsString('Data telah berubah', $this->failureMessages(fn () => $svc->decide($c2, $this->s->u['verifikator'], true, null)));
        $this->assertSame(2, DataCorrection::count());
    }

    public function test_koreksi_nomor_sp2d_oleh_bpkad_disetujui_oleh_pihak_berwenang(): void
    {
        $app = $this->appAt(Status::DISBURSED);
        $disb = $app->disbursements()->first();
        $svc = app(CorrectionService::class);
        $c = $svc->request($app, $this->s->u['opd'], 'disbursement', $disb->id, 'sp2d_no', 'SP2D-LS/BENAR', 'Salah catat nomor SP2D');
        $svc->decide($c, $this->s->u['bpkad'], true, 'OK');
        $this->assertSame('SP2D-LS/BENAR', $disb->fresh()->sp2d_no);
    }


    public function test_perintah_buat_admin_membuat_akun_admin_tanpa_peran_proses(): void
    {
        $this->artisan('ehibah:create-admin', ['email' => 'Admin.Baru@Kepahiang.test', 'name' => 'Admin Baru'])->assertSuccessful();
        $u = User::where('email', 'admin.baru@kepahiang.test')->first();
        $this->assertSame(['ADMIN'], $u->roleCodes());
        $this->assertTrue((bool) $u->is_active);
        $this->assertTrue(AuditLog::where('action', 'ADMIN_CREATED_CLI')->exists());
        $this->artisan('ehibah:create-admin', ['email' => 'admin.baru@kepahiang.test', 'name' => 'Dobel'])->assertFailed();
        $this->artisan('ehibah:create-admin', ['email' => 'x@x.test', 'name' => 'X', '--password' => 'pendek'])->assertFailed();
        // admin tidak boleh merangkap peran proses lewat UI
        $this->actingAs($this->s->u['admin'])->put("/admin/pengguna/{$u->id}", [
            'account_status' => 'ACTIVE', 'roles' => ['ADMIN', 'BPKAD'], 'organization_id' => $this->s->opd->id,
        ])->assertSessionHasErrors('roles');
    }
    public function test_hash_password_dan_tidak_disimpan_polos(): void
    {
        $u = $this->s->u['pemohon']->fresh();
        $this->assertNotSame(ScenarioBuilderPassword::get(), $u->getRawOriginal('password'));
        $this->assertTrue(Hash::check(ScenarioBuilderPassword::get(), $u->password));
        $this->assertArrayNotHasKey('password', $u->toArray());
    }
}

/** Pembungkus agar kata sandi demo konsisten di tes. */
final class ScenarioBuilderPassword
{
    public static function get(): string
    {
        return \App\Support\ScenarioBuilder::password();
    }
}
