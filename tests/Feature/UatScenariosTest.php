<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\Disbursement;
use App\Models\Regulation;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\DisbursementService;
use App\Services\LpjMonitor;
use App\Services\ProcessService;
use App\Services\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\UsesScenario;
use Tests\TestCase;

/**
 * Skenario UAT Blueprint Bab 12 (16 skenario) + Matriks Kepatuhan (UAT-001..016).
 * Setiap tes mengeksekusi layanan sebenarnya dan memeriksa penolakan di sisi server.
 */
class UatScenariosTest extends TestCase
{
    use UsesScenario;

    public function test_blueprint_01_pengajuan_tanpa_dokumen_lengkap_ditolak(): void
    {
        $app = $this->s->newApplication();
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'submit', 'pemohon'));
        $this->assertStringContainsString('Dokumen wajib belum diunggah', $msg);
        $this->assertSame(Status::DRAFT, $app->fresh()->status);
    }

    public function test_blueprint_02_perbaikan_dokumen_membuat_versi_baru_dan_riwayat_tetap(): void
    {
        $app = $this->appAt(Status::ADMIN_CHECK);
        $this->s->act($app, 'admin_return', 'opd', ['note' => 'Proposal kurang jelas']);
        $this->assertSame(Status::RETURNED, $app->fresh()->status);

        $file = \Illuminate\Http\Testing\File::createWithContent('proposal-baru.pdf', "%PDF-1.4\nrevisi\n%%EOF");
        app(\App\Services\DocumentService::class)->store($app->fresh(), $this->s->u['pemohon'], 'PENGAJUAN', 'PROPOSAL', $file);

        $versions = ApplicationDocument::where('application_id', $app->id)->where('doc_type', 'PROPOSAL')->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertFalse((bool) $versions[0]->is_current);
        $this->assertTrue((bool) $versions[1]->is_current);
        Storage::disk('local')->assertExists($versions[0]->file_path); // versi lama tidak dihapus

        $re = $this->s->act($app, 'submit', 'pemohon');
        $this->assertSame(Status::SUBMITTED, $re->status);
        $this->assertSame(1, $re->revision_no);
    }

    public function test_blueprint_03_akses_di_luar_kewenangan_ditolak_dan_tercatat(): void
    {
        $this->actingAs($this->s->u['pemohon'])->get('/audit')->assertForbidden();
        $this->actingAs($this->s->u['pemohon'])->get('/pagu')->assertForbidden();
        $this->actingAs($this->s->u['opd'])->get('/admin/pengguna')->assertForbidden();

        $this->assertGreaterThanOrEqual(3, AuditLog::where('action', 'ACCESS_DENIED')->count());
        $this->assertSame($this->s->u['pemohon']->id, AuditLog::where('action', 'ACCESS_DENIED')->orderBy('id')->first()->user_id);
    }

    public function test_blueprint_04_persetujuan_di_luar_kewenangan_tidak_tersedia(): void
    {
        $app = $this->appAt(Status::TAPD_REVIEW);
        $wf = app(WorkflowService::class);
        foreach (['opd', 'tapd', 'pemohon', 'bpkad', 'inspektorat'] as $who) {
            $this->assertArrayNotHasKey('approve', $wf->availableActions($app, $this->s->u[$who]), "$who tidak boleh menetapkan");
        }
        $this->assertArrayHasKey('approve', $wf->availableActions($app, $this->s->u['bupati']));

        $this->expectException(AuthorizationException::class);
        $wf->perform($app, 'approve', $this->s->u['opd'], ['decree_id' => 1, 'amount_approved' => 1]);
    }

    public function test_blueprint_05_usulan_tidak_direkomendasikan_tidak_masuk_penetapan(): void
    {
        $app = $this->appAt(Status::TECH_CHECK);
        // skor rendah => hasil teknis tidak layak
        app(ProcessService::class)->recordTechnical($app, $this->s->u['verifikator'], ['relevansi' => 20, 'rab' => 30, 'manfaat' => 40, 'kesiapan' => 10], 'Tidak layak');

        $msg = $this->failureMessages(fn () => $this->s->act($app, 'recommend', 'opd', [
            'letter_no' => 'X/1', 'letter_date' => now()->toDateString(), 'amount_recommended' => 1000,
        ]));
        $this->assertStringContainsString('tidak layak', $msg);
        // dan tidak dapat melompat ke penetapan
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->s->act($app, 'approve', 'bupati', ['decree_id' => 1, 'amount_approved' => 1000]);
    }

    public function test_blueprint_06_disetujui_tanpa_alokasi_anggaran_diblokir(): void
    {
        $app = $this->appAt(Status::TAPD_REVIEW);
        $decree = \App\Models\Decree::create([
            'fiscal_year_id' => $app->fiscal_year_id, 'decree_no' => 'KEP/1', 'decree_date' => '2026-06-01', 'title' => 't', 'perda_ref' => 'p', 'perkada_ref' => 'k', 'created_by' => $this->s->u['tapd']->id,
        ]);
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'approve', 'bupati', ['decree_id' => $decree->id, 'amount_approved' => 1000]));
        $this->assertStringContainsString('Alokasi anggaran belum tersedia', $msg);
        $this->assertStringContainsString('Pertimbangan TAPD belum dicatat', $msg);
    }

    public function test_blueprint_06b_alokasi_melebihi_pagu_diblokir(): void
    {
        $app = $this->appAt(Status::TAPD_REVIEW, ['amount' => 100_000_000]);
        $ceiling = \App\Models\BudgetCeiling::where('opd_id', $app->opd_id)->first();
        $ceiling->update(['ceiling' => 50_000_000]);
        $msg = $this->failureMessages(fn () => app(ProcessService::class)->recordTapdReview($app->fresh(), $this->s->u['tapd'], 100_000_000, ''));
        $this->assertStringContainsString('melebihi sisa pagu', $msg);
        $this->assertNull($app->fresh()->allocation);
    }

    public function test_blueprint_07_nphd_belum_lengkap_penyaluran_tidak_dapat_diajukan(): void
    {
        $app = $this->appAt(Status::BUDGETED);
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'sign_nphd', 'opd', ['signature_method' => 'BASAH', 'signed_at' => now()->toDateString()]));
        $this->assertStringContainsString('NPHD belum disusun', $msg);

        $msg = $this->failureMessages(fn () => app(DisbursementService::class)->request($app->fresh(), $this->s->u['opd'], 1, (string) Str::uuid(), null, null));
        $this->assertStringContainsString('NPHD belum ditandatangani', $msg);
    }

    public function test_blueprint_08_penyaluran_melebihi_nilai_nphd_ditolak_server(): void
    {
        $app = $this->appAt(Status::NPHD_SIGNED);
        $this->s->uploadAll($app, 'PENCAIRAN');
        $app->activeNphd()->update(['amount' => 10_000_000]); // NPHD dikecilkan: 70% tahap I = 70 jt > 10 jt
        $msg = $this->failureMessages(fn () => app(DisbursementService::class)->request($app->fresh(), $this->s->u['opd'], 1, (string) Str::uuid(), null, null));
        $this->assertStringContainsString('melebihi nilai NPHD', $msg);
    }

    public function test_blueprint_09_pembayaran_ganda_hanya_satu_transaksi(): void
    {
        $app = $this->appAt(Status::NPHD_SIGNED);
        $this->s->uploadAll($app, 'PENCAIRAN');
        $svc = app(DisbursementService::class);
        $key = (string) Str::uuid();

        $a = $svc->request($app->fresh(), $this->s->u['opd'], 1, $key, 'SPP/1', now()->toDateString());
        $b = $svc->request($app->fresh(), $this->s->u['opd'], 1, $key, 'SPP/1', now()->toDateString()); // klik ganda / retry
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Disbursement::count());

        $msg = $this->failureMessages(fn () => $svc->request($app->fresh(), $this->s->u['opd'], 1, (string) Str::uuid(), null, null)); // kunci baru, tahap sama
        $this->assertStringContainsString('Pembayaran ganda ditolak', $msg);
        $this->assertSame(1, Disbursement::count());

        // pemrosesan SP2D dua kali
        $data = ['spm_no' => 'S1', 'sp2d_no' => 'P1', 'sp2d_date' => now()->toDateString(), 'rkud_ref' => 'R1', 'bank_account_ref' => 'B1'];
        $svc->process($a, $this->s->u['bpkad'], $data);
        $msg = $this->failureMessages(fn () => $svc->process($a->fresh(), $this->s->u['bpkad'], $data));
        $this->assertStringContainsString('sudah diproses', $msg);
        $this->assertSame(70_000_000, $app->fresh()->disbursedTotal());
    }

    public function test_blueprint_10_hibah_barang_tidak_bisa_lewat_jalur_uang(): void
    {
        $app = $this->appAt(Status::NPHD_SIGNED, ['grant_type' => 'HIBAH_BARANG', 'amount' => 40_000_000]);
        $msg = $this->failureMessages(fn () => app(DisbursementService::class)->request($app->fresh(), $this->s->u['opd'], 1, (string) Str::uuid(), null, null));
        $this->assertStringContainsString('Hibah barang tidak melalui pencairan uang', $msg);
        $this->assertSame(0, Disbursement::count());
    }

    public function test_blueprint_11_lpj_melewati_batas_waktu_jadi_tunggakan_dan_notifikasi(): void
    {
        $app = $this->appAt(Status::DISBURSED);
        $app->update(['lpj_due_date' => now()->subDays(3)->toDateString()]);

        $r = app(LpjMonitor::class)->run();
        $this->assertSame(1, $r['overdue']);
        $this->assertSame(1, $r['warnings']);
        $this->assertTrue((bool) $app->fresh()->lpj_overdue);
        $this->assertSame(1, $app->warnings()->count());
        $this->assertGreaterThan(0, $this->s->u['pemohon']->notifications()->where('data', 'like', '%melewati tenggat%')->count());

        // dijalankan ulang di hari yang sama tidak menduplikasi surat peringatan
        $r2 = app(LpjMonitor::class)->run();
        $this->assertSame(0, $r2['warnings']);
        // 8 hari kemudian terbit peringatan ke-2
        $r3 = app(LpjMonitor::class)->run(now()->addDays(8));
        $this->assertSame(1, $r3['warnings']);
        $this->assertSame(2, $app->warnings()->max('level'));
    }

    public function test_blueprint_11b_pengingat_sebelum_tenggat(): void
    {
        $app = $this->appAt(Status::DISBURSED);
        $app->update(['lpj_due_date' => now()->addDays(3)->toDateString()]);
        $this->assertSame(1, app(LpjMonitor::class)->run()['reminders']);
        $this->assertSame(0, app(LpjMonitor::class)->run()['reminders']); // tidak dobel
    }

    public function test_blueprint_12_lpj_tidak_lengkap_dikembalikan_dengan_catatan(): void
    {
        $app = $this->appAt(Status::LPJ_REVIEW);
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'lpj_return', 'opd', []));
        $this->assertStringContainsString('wajib diisi', $msg);

        $back = $this->s->act($app, 'lpj_return', 'opd', ['note' => 'Bukti pengeluaran kurang']);
        $this->assertSame(Status::DISBURSED, $back->status);
        $r = $back->reports()->where('report_type', 'AKHIR')->first();
        $this->assertSame('PERLU_PERBAIKAN', $r->review_status);
        $this->assertSame('Bukti pengeluaran kurang', $r->review_notes);
    }

    public function test_blueprint_13_sisa_dana_wajib_disetor_sebelum_penutupan(): void
    {
        $app = $this->appAt(Status::LPJ_REVIEW); // realisasi 95% => sisa 5 jt
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'close', 'opd'));
        $this->assertStringContainsString('Sisa dana', $msg);
        $this->assertStringContainsString('Belum ada catatan monitoring', $msg);

        app(\App\Services\AccountabilityService::class)->recordReturn($app->fresh(), $this->s->u['pemohon'], ['amount' => 5_000_000, 'returned_on' => now()->toDateString(), 'kas_ref' => 'STS/9']);
        $this->assertSame(5_000_000, $app->fresh()->returnedTotal());
        // setoran melebihi dana tersalur ditolak
        $msg = $this->failureMessages(fn () => app(\App\Services\AccountabilityService::class)->recordReturn($app->fresh(), $this->s->u['pemohon'], ['amount' => 999_000_000, 'returned_on' => now()->toDateString(), 'kas_ref' => 'STS/10']));
        $this->assertStringContainsString('melebihi', $msg);
    }

    public function test_blueprint_14_log_audit_tidak_dapat_diubah_atau_dihapus(): void
    {
        app(AuditService::class)->access('TES_AWAL');
        $id = AuditLog::first()->id;

        foreach ([
            fn () => DB::table('audit_logs')->where('id', $id)->update(['action' => 'DIUBAH']),
            fn () => DB::table('audit_logs')->where('id', $id)->delete(),
            fn () => AuditLog::query()->update(['action' => 'X']),
            fn () => AuditLog::first()->delete(),
            fn () => AuditLog::first()->update(['action' => 'X']),
        ] as $i => $attempt) {
            try {
                DB::transaction($attempt); // savepoint: pada PostgreSQL kegagalan membatalkan transaksi tes
                $this->fail("Percobaan #$i seharusnya gagal");
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('TES_AWAL', AuditLog::find($id)->action);
        $this->assertNull(app(AuditService::class)->verifyChain());
    }

    public function test_blueprint_14b_rantai_hash_mendeteksi_manipulasi_langsung(): void
    {
        $audit = app(AuditService::class);
        $audit->access('A');
        $audit->access('B');
        $audit->access('C');
        $this->assertNull($audit->verifyChain());

        // penyerang dengan akses DB menonaktifkan pengaman lalu mengubah baris tengah
        DB::unprepared(DB::getDriverName() === 'pgsql' ? 'DROP TRIGGER audit_logs_no_update ON audit_logs' : 'DROP TRIGGER audit_logs_no_update');
        DB::table('audit_logs')->where('action', 'B')->update(['action' => 'B-DIUBAH']);
        $broken = $audit->verifyChain();
        $this->assertNotNull($broken);
        $this->assertStringContainsString('hash tidak cocok', $broken['reason']);
    }

    public function test_blueprint_15_backup_dan_verifikasi_keutuhan(): void
    {
        $app = $this->appAt(Status::SUBMITTED);
        $svc = app(BackupService::class);
        config(['ehibah.backup_path' => storage_path('framework/testing/backup-'.Str::random(6))]);

        $log = $svc->run('FILES', $this->s->u['admin']);
        $this->assertSame('BERHASIL', $log->status, (string) $log->message);
        $this->assertTrue($svc->verify($log->fresh()));

        // berkas backup diubah => verifikasi gagal
        file_put_contents($log->path, 'korup', FILE_APPEND);
        $this->assertFalse($svc->verify($log->fresh()));
        @unlink($log->path);
    }

    public function test_blueprint_16_tampilan_responsif_ponsel(): void
    {
        $this->get('/')->assertOk()->assertSee('width=device-width', false);
        $this->actingAs($this->s->u['pemohon'])->get('/dashboard')->assertOk()
            ->assertSee('bottom-nav', false)->assertSee('width=device-width', false);
        $css = file_get_contents(public_path('css/app.css'));
        $this->assertStringContainsString('@media (max-width:860px)', $css);
        $this->assertStringContainsString('table.responsive', $css);
    }

    // ---------------------------------------------------------------- Matriks Kepatuhan
    public function test_matriks_uat001_peruntukan_kosong_menahan_rekomendasi(): void
    {
        $app = $this->appAt(Status::TECH_CHECK);
        $app->update(['purpose' => '', 'program_activity' => '']);
        app(ProcessService::class)->recordTechnical($app->fresh(), $this->s->u['verifikator'], ['relevansi' => 90, 'rab' => 90, 'manfaat' => 90, 'kesiapan' => 90], null);
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'recommend', 'opd', ['letter_no' => 'R/1', 'letter_date' => now()->toDateString(), 'amount_recommended' => 1000]));
        $this->assertStringContainsString('Peruntukan hibah harus spesifik', $msg);
    }

    public function test_matriks_uat002_dokumen_legalitas_wajib_per_kategori(): void
    {
        $app = $this->s->newApplication();
        $codes = (new \App\Services\DocumentChecklist)->requirementsFor($app, 'PENGAJUAN')->pluck('code')->all();
        foreach (['AKTA_BADAN_HUKUM', 'SK_KEPENGURUSAN', 'DOMISILI', 'NPWP', 'REKENING'] as $c) {
            $this->assertContains($c, $codes);
        }
        $this->assertNotContains('AD_ART', $codes);
        // dokumen berjangka: tanpa tanggal berlaku ditolak
        $file = \Illuminate\Http\Testing\File::createWithContent('d.pdf', "%PDF-1.4\n%%EOF");
        $msg = $this->failureMessages(fn () => app(\App\Services\DocumentService::class)->store($app, $this->s->u['pemohon'], 'PENGAJUAN', 'DOMISILI', $file));
        $this->assertStringContainsString('masa berlaku', $msg);
    }

    public function test_matriks_uat003_proposal_lengkap_menghasilkan_rekomendasi_sesuai_data(): void
    {
        $app = $this->appAt(Status::RECOMMENDED, ['amount' => 150_000_000]);
        $r = $app->recommendation;
        $this->assertSame(150_000_000, $r->amount_recommended);
        $this->actingAs($this->s->u['opd'])->get(route('print', [$app, 'rekomendasi']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_matriks_uat004_penetapan_tanpa_keputusan_ditolak(): void
    {
        $app = $this->appAt(Status::TAPD_REVIEW);
        app(ProcessService::class)->recordTapdReview($app->fresh(), $this->s->u['tapd'], (int) $app->amount_recommended, null);
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'approve', 'bupati', ['amount_approved' => 1000]));
        $this->assertStringContainsString('Keputusan Bupati', $msg);
        $this->assertNotSame(Status::APPROVED, $app->fresh()->status);
    }

    public function test_matriks_uat005_penyaluran_tanpa_nphd_diblokir(): void
    {
        $app = $this->appAt(Status::BUDGETED);
        $this->assertSame(['sign_nphd'], array_values(array_intersect(array_keys(app(WorkflowService::class)->availableActions($app, $this->s->u['opd'])), ['sign_nphd', 'mark_disbursed'])));
        $msg = $this->failureMessages(fn () => app(DisbursementService::class)->request($app->fresh(), $this->s->u['opd'], 1, (string) Str::uuid(), null, null));
        $this->assertNotSame('', $msg);
    }

    public function test_matriks_uat006_pembagian_70_30(): void
    {
        $calc = new \App\Services\GrantCalculator;
        $plan = $calc->stagePlan(100_000_000);
        $this->assertSame(70_000_000, $plan[1]['amount']);
        $this->assertSame(30_000_000, $plan[2]['amount']);
        // pembulatan: selisih masuk tahap II sehingga total selalu persis
        $plan = $calc->stagePlan(100_000_001);
        $this->assertSame(100_000_001, $plan[1]['amount'] + $plan[2]['amount']);
        $plan = $calc->stagePlan(33_333_333);
        $this->assertSame(33_333_333, $plan[1]['amount'] + $plan[2]['amount']);
    }

    public function test_matriks_uat007_pengecualian_100_persen_butuh_alasan_dan_otorisasi(): void
    {
        $app = $this->appAt(Status::NPHD_SIGNED);
        $svc = app(DisbursementService::class);
        $this->assertStringContainsString('15', $this->failureMessages(fn () => $svc->requestFullException($app, $this->s->u['opd'], 'EVENT', 'singkat')));

        $svc->requestFullException($app->fresh(), $this->s->u['opd'], 'INSTANSI_VERTIKAL', 'Penerima instansi vertikal, tidak memungkinkan bertahap.');
        $this->assertSame('STAGED', $app->fresh()->disbursement_scheme); // belum otomatis aktif

        $this->expectException(AuthorizationException::class);
        $svc->authorizeFullException($app->fresh(), $this->s->u['opd'], true); // OPD tidak berwenang memberi otorisasi
    }

    public function test_matriks_uat007b_pengecualian_diotorisasi_bpkad_penyaluran_sekaligus(): void
    {
        $app = $this->appAt(Status::NPHD_SIGNED);
        $svc = app(DisbursementService::class);
        $svc->requestFullException($app, $this->s->u['opd'], 'EVENT', 'Kegiatan event tunggal yang tidak dapat dipecah.');
        $svc->authorizeFullException($app->fresh(), $this->s->u['bpkad'], true);
        $app = $app->fresh();
        $this->assertSame('FULL', $app->disbursement_scheme);
        $this->assertSame([1], array_keys((new \App\Services\GrantCalculator)->stagePlan(100, 'FULL')));
        $this->s->disburseMoney($app, 1);
        $this->assertSame(100_000_000, $app->fresh()->disbursedTotal());
        $this->assertSame(100.0, (float) $app->disbursements()->first()->percentage);
    }

    public function test_matriks_uat008_tahap_ii_terkunci_sebelum_laporan_tahap_i(): void
    {
        $app = $this->appAt(Status::DISBURSED);
        $msg = $this->failureMessages(fn () => app(DisbursementService::class)->request($app->fresh(), $this->s->u['opd'], 2, (string) Str::uuid(), null, null));
        $this->assertStringContainsString('Laporan penggunaan tahap I belum diterima', $msg);
    }

    public function test_matriks_uat009_hibah_barang_tanpa_bast_tidak_selesai(): void
    {
        $app = $this->appAt(Status::NPHD_SIGNED, ['grant_type' => 'HIBAH_BARANG', 'amount' => 30_000_000]);
        $goods = app(\App\Services\GoodsService::class);
        $goods->save($app, $this->s->u['opd'], [
            'procurement_ref' => 'K/1', 'procurement_date' => now()->toDateString(), 'bast_no' => 'B/1', 'bast_date' => now()->toDateString(), 'received_by_name' => 'X',
        ], [['description' => 'Meja', 'quantity' => 3, 'unit' => 'unit', 'unit_value' => 1000]]);
        $msg = $this->failureMessages(fn () => $goods->markDelivered($app->fresh(), $this->s->u['opd']));
        $this->assertStringContainsString('BAST', $msg);
        $this->assertSame(Status::NPHD_SIGNED, $app->fresh()->status);
        // pencatatan aset sebelum penyerahan ditolak
        $this->failureMessages(fn () => $goods->recordAsset($app->fresh(), $this->s->u['bpkad'], 'KIB/1'));
    }

    public function test_matriks_uat010_lpj_kurang_bukti_status_perlu_perbaikan(): void
    {
        $app = $this->appAt(Status::DISBURSED);
        // tanpa dokumen LPJ, pengajuan akhir ditolak dengan rincian kekurangan
        $acc = app(\App\Services\AccountabilityService::class);
        $acc->setActivityCompleted($app, $this->s->u['pemohon'], now()->subDay()->toDateString());
        $acc->saveDraft($app->fresh(), $this->s->u['pemohon'], 'TAHAP_1', ['summary' => 's'], [['spent_on' => now()->toDateString(), 'description' => 'x', 'amount' => 1000]]);
        $acc->saveDraft($app->fresh(), $this->s->u['pemohon'], 'AKHIR', ['summary' => 's', 'activity_result' => 'h'], [['spent_on' => now()->toDateString(), 'description' => 'x', 'amount' => 1000]]);
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'submit_lpj', 'pemohon'));
        $this->assertStringContainsString('Dokumen LPJ belum diunggah', $msg);
    }

    public function test_matriks_uat011_perhitungan_tenggat_lpj(): void
    {
        $c = new \App\Services\GrantCalculator;
        $this->assertSame('2026-12-30', $c->lpjDueDate(\Illuminate\Support\Carbon::parse('2026-11-30'), 2026)->toDateString()); // 1 bulan
        $this->assertSame('2027-01-10', $c->lpjDueDate(\Illuminate\Support\Carbon::parse('2026-12-20'), 2026)->toDateString()); // dibatasi 10 Januari
        $this->assertSame('2027-01-10', $c->lpjDueDate(\Illuminate\Support\Carbon::parse('2026-12-10'), 2026)->toDateString());
        \App\Models\Setting::create(['key' => 'lpj.cap_enabled', 'value' => 'false']);
        \App\Support\Setting::forget();
        $this->assertSame('2027-01-20', $c->lpjDueDate(\Illuminate\Support\Carbon::parse('2026-12-20'), 2026)->toDateString()); // penafsiran hukum lain
    }

    public function test_matriks_uat012_sisa_hibah_100_juta_realisasi_95_juta(): void
    {
        $c = new \App\Services\GrantCalculator;
        $this->assertSame(5_000_000, $c->remainingFunds(100_000_000, 95_000_000));
        $this->assertSame(0, $c->remainingFunds(100_000_000, 120_000_000));
        $app = $this->appAt(Status::LPJ_REVIEW);
        $this->assertSame(5_000_000, $app->reports()->where('report_type', 'AKHIR')->first()->remaining_funds);
    }

    public function test_matriks_uat013_monitoring_dikirim_ke_bupati_tembusan_inspektorat(): void
    {
        $app = $this->appAt(Status::DISBURSED);
        $m = app(\App\Services\AccountabilityService::class)->recordMonitoring($app, $this->s->u['opd'], [
            'type' => 'MONEV', 'visited_at' => now()->toDateString(), 'findings' => 'Kegiatan berjalan sesuai rencana.', 'follow_up' => 'Lengkapi dokumentasi',
        ]);
        $this->assertNotNull($m->sent_to_bupati_at);
        $this->assertNotNull($m->sent_to_inspektorat_at);
        $this->assertSame('OPEN', $m->follow_up_status);
        $this->assertGreaterThan(0, $this->s->u['bupati']->notifications()->count());
        $this->assertGreaterThan(0, $this->s->u['inspektorat']->notifications()->count());
    }

    public function test_matriks_uat014_parpol_memiliki_checklist_dan_syarat_khusus(): void
    {
        $parpol = $this->s->applicantOrg('PARPOL-X', 'Partai Contoh', 'PARPOL');
        $app = $this->s->newApplication(['applicant' => $parpol]);
        $this->assertTrue($app->isParpol());
        $codes = (new \App\Services\DocumentChecklist)->requirementsFor($app, 'PENGAJUAN')->pluck('code')->all();
        $this->assertContains('AD_ART', $codes);
        $this->assertContains('REKOM_KESBANGPOL', $codes);
        $pencairan = (new \App\Services\DocumentChecklist)->requirementsFor($app, 'PENCAIRAN')->pluck('code')->all();
        $this->assertContains('PERMOHONAN_PARPOL', $pencairan);
        $this->assertContains('BA_VERIF_PARPOL', $pencairan);
        // sekretaris wajib
        $parpol->update(['secretary_name' => null]);
        $this->s->u['parpol'] = $this->s->user('pemohon.parpol@ehibah.test', 'Ketua Partai', 'PEMOHON', $parpol, 'Ketua');
        $msg = $this->failureMessages(fn () => $this->s->act($app->fresh(), 'submit', 'parpol'));
        $this->assertStringContainsString('ketua dan sekretaris', $msg);
    }

    public function test_matriks_uat015_bansos_tidak_masuk_alur_hibah(): void
    {
        $bansos = \App\Models\GrantType::where('code', 'BANSOS')->first();
        $this->assertFalse((bool) $bansos->is_active);
        $app = $this->s->newApplication();
        $app->update(['grant_type_id' => $bansos->id]);
        $this->s->uploadAll($app->fresh(), 'PENGAJUAN');
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'submit', 'pemohon'));
        $this->assertStringContainsString('bansos', mb_strtolower($msg));

        // lewat form: ditolak validasi
        $r = $this->actingAs($this->s->u['pemohon'])->post('/usulan', ['grant_type_id' => $bansos->id] + $this->formPayload());
        $r->assertSessionHasErrors('grant_type_id');
    }

    public function test_matriks_uat016_regulasi_dicabut_tidak_bisa_jadi_dasar_aktif(): void
    {
        $lama = Regulation::where('code', 'PERBUP-KPH-18-2014')->first();
        $this->assertSame('DICABUT', $lama->status);
        $this->assertSame('PERBUP-KPH-25-2023', $lama->revokedBy->code);

        $app = $this->s->newApplication();
        $app->update(['regulation_id' => $lama->id]);
        $this->s->uploadAll($app->fresh(), 'PENGAJUAN');
        $msg = $this->failureMessages(fn () => $this->s->act($app, 'submit', 'pemohon'));
        $this->assertStringContainsString('Dasar hukum yang dipilih tidak berlaku', $msg);
    }

    private function formPayload(): array
    {
        return [
            'opd_id' => $this->s->opd->id, 'regulation_id' => Regulation::where('code', 'PERBUP-KPH-25-2023')->value('id'), 'title' => 'Judul',
            'background' => 'a', 'purpose' => 'b', 'target' => 'c', 'program_activity' => 'd', 'location' => 'Kepahiang',
            'activity_start' => '2026-03-01', 'activity_end' => '2026-04-01',
            'committee' => [['nama' => 'A', 'jabatan' => 'Ketua']],
            'items' => [['description' => 'x', 'quantity' => 1, 'unit' => 'paket', 'unit_price' => 1000]],
        ];
    }
}
