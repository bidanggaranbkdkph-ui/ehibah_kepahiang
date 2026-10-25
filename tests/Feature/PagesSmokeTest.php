<?php

namespace Tests\Feature;

use App\Models\GrantApplication;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menjelajahi seluruh halaman untuk tiap peran dengan data demo lengkap (seluruh status),
 * memastikan tidak ada galat tampilan (500) dan batas akses (403) sesuai matriks.
 */
class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const EMAILS = [
        'pemohon' => 'pemohon@ehibah.test', 'opd' => 'opd@ehibah.test', 'kadis' => 'kadis@ehibah.test', 'verifikator' => 'verifikator@ehibah.test',
        'tapd' => 'tapd@ehibah.test', 'bupati' => 'bupati@ehibah.test', 'bpkad' => 'bpkad@ehibah.test', 'inspektorat' => 'inspektorat@ehibah.test',
    ];

    public function test_seluruh_halaman_terender_untuk_setiap_peran(): void
    {
        Storage::fake('local');
        $this->seed(ReferenceSeeder::class);
        $this->seed(DemoSeeder::class);

        // publik
        foreach (['/', '/masuk', '/daftar', '/cek-status'] as $url) {
            $this->get($url)->assertOk();
        }
        $reg = GrantApplication::whereNotNull('reg_number')->value('reg_number');
        $this->get('/cek-status?reg='.urlencode($reg))->assertOk()->assertSee($reg)->assertDontSee('Yayasan');
        $this->get('/cek-status?reg=TIDAK-ADA')->assertOk()->assertSee('tidak ditemukan');

        $common = ['/dashboard', '/usulan', '/notifikasi', '/profil', '/hak-akses'];
        $extra = [
            'pemohon' => ['/usulan/baru'],
            'opd' => ['/verifikasi', '/monev', '/koreksi', '/laporan', '/pagu', '/keputusan'],
            'kadis' => ['/verifikasi'],
            'verifikator' => ['/verifikasi', '/laporan', '/koreksi'],
            'tapd' => ['/verifikasi', '/pagu', '/keputusan', '/laporan', '/monev'],
            'bupati' => ['/verifikasi', '/keputusan', '/laporan', '/monev'],
            'bpkad' => ['/verifikasi', '/pagu', '/laporan', '/monev', '/audit', '/koreksi'],
            'inspektorat' => ['/verifikasi', '/laporan', '/monev', '/audit', '/koreksi', '/pagu'],
        ];
        foreach (self::EMAILS as $role => $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->actingAs($user);

            foreach (array_merge($common, $extra[$role] ?? []) as $url) {
                $this->get($url)->assertOk();
            }
            // setiap usulan yang boleh dilihat peran ini harus dapat dibuka seluruhnya
            $visible = GrantApplication::query()->visibleTo($user)->pluck('id');
            foreach ($visible as $id) {
                $this->get("/usulan/$id")->assertOk();
            }
            if ($role === 'pemohon') {
                $this->assertGreaterThan(5, $visible->count());
            }
        }

        // laporan: halaman + Excel + PDF untuk setiap laporan
        $this->actingAs(User::where('email', self::EMAILS['inspektorat'])->first());
        foreach (array_keys(ReportService::CATALOG) as $slug) {
            $this->get("/laporan/$slug")->assertOk();
            $x = $this->get("/laporan/$slug/ekspor/xlsx");
            $x->assertOk();
            $this->assertStringContainsString('spreadsheetml', $x->headers->get('content-type'));
            $this->get("/laporan/$slug/ekspor/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        }
        $decree = \App\Models\Decree::first();
        $this->actingAs(User::where('email', self::EMAILS['bupati'])->first())
            ->get("/keputusan/{$decree->id}/nominatif?format=pdf")->assertOk();
        $this->get("/keputusan/{$decree->id}/nominatif?format=xlsx")->assertOk();

        // cetak dokumen resmi
        $closed = GrantApplication::where('status', 'CLOSED')->whereHas('grantType', fn ($q) => $q->where('form', 'UANG'))->first();
        $this->actingAs(User::where('email', self::EMAILS['opd'])->first());
        foreach (['rekomendasi', 'nphd', 'pakta', 'tanggung-jawab', 'berita-acara'] as $t) {
            $this->get(route('print', [$closed, $t]))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
        $goods = GrantApplication::whereHas('nphds')->whereHas('grantType', fn ($q) => $q->where('form', 'BARANG'))->first();
        $this->get(route('print', [$goods, 'nphd']))->assertOk();
        $late = GrantApplication::where('lpj_overdue', true)->first();
        $this->get(route('print', [$late, 'peringatan']))->assertOk();

        // unduh dokumen privat: terotorisasi + tercatat
        $doc = $closed->documents()->first();
        $this->get(route('documents.download', $doc))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'DOCUMENT_DOWNLOADED']);
    }

    public function test_admin_hanya_melihat_halaman_administrasi_dan_tidak_data_permohonan(): void
    {
        Storage::fake('local');
        $this->seed(ReferenceSeeder::class);
        $this->seed(DemoSeeder::class);
        $admin = User::where('email', 'admin@ehibah.test')->first();
        $this->actingAs($admin);

        foreach (['/dashboard', '/profil', '/hak-akses', '/admin/pengguna', '/admin/konfigurasi', '/admin/backup', '/audit', '/notifikasi'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['organisasi', 'tahun-anggaran', 'jenis-hibah', 'kategori-penerima', 'dokumen-wajib', 'regulasi'] as $m) {
            $this->get("/admin/master/$m")->assertOk();
        }
        // tidak punya akses data permohonan
        $id = GrantApplication::value('id');
        $this->get("/usulan/$id")->assertForbidden();
        $this->get('/laporan')->assertForbidden();
        $this->get('/pagu')->assertForbidden();
        $this->get('/keputusan')->assertForbidden();
        // log yang dilihat admin hanya kategori sistem/akses, bukan jejak proses
        $this->get('/audit?category=PROSES')->assertOk()->assertDontSee('STATUS_SUBMIT');
    }

    public function test_pemohon_lain_dan_opd_lain_tidak_dapat_membuka_usulan(): void
    {
        Storage::fake('local');
        $this->seed(ReferenceSeeder::class);
        $this->seed(DemoSeeder::class);

        $app = GrantApplication::where('applicant_org_id', \App\Models\Organization::where('code', 'YAYASAN-CB')->value('id'))->whereHas('documents')->first();
        $other = User::where('email', 'pemohon.pmi@ehibah.test')->first();
        $this->actingAs($other)->get("/usulan/{$app->id}")->assertForbidden();
        $this->get('/dokumen/'.$app->documents()->value('id'))->assertForbidden();

        // OPD lain (Dinas Kesehatan) tidak boleh melihat usulan Dinas Pendidikan
        $s = new \App\Support\ScenarioBuilder;
        $dinkes = \App\Models\Organization::where('code', 'DINKES')->first();
        $kesUser = $s->user('opd.kes@ehibah.test', 'Staf Dinkes', 'OPD', $dinkes, 'Staf');
        $this->actingAs($kesUser)->get("/usulan/{$app->id}")->assertForbidden();
        $this->actingAs($kesUser)->post("/usulan/{$app->id}/aksi/start_admin")->assertForbidden();
    }
}
