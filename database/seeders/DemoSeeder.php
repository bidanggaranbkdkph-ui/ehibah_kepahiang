<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\BudgetCeiling;
use App\Models\FiscalYear;
use App\Models\Organization;
use App\Support\ScenarioBuilder;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk demo/pelatihan (BUKAN untuk produksi). Seluruh usulan dibangun lewat layanan
 * yang sebenarnya, sehingga setiap status memuat riwayat, dokumen, dan audit trail yang konsisten.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoSeeder dilewati di production.');

            return;
        }
        $s = new ScenarioBuilder;
        $fy = FiscalYear::where('year', 2026)->first();

        // OPD & pagu tambahan
        $kes = Organization::firstOrCreate(['code' => 'DINKES'], ['name' => 'Dinas Kesehatan', 'type' => 'OPD', 'address' => 'Kepahiang']);
        $tan = Organization::firstOrCreate(['code' => 'DISTAN'], ['name' => 'Dinas Pertanian', 'type' => 'OPD', 'address' => 'Kepahiang']);
        foreach ([[$kes, 3_000_000_000], [$tan, 2_000_000_000]] as [$o, $c]) {
            BudgetCeiling::firstOrCreate(['fiscal_year_id' => $fy->id, 'opd_id' => $o->id], ['ceiling' => $c, 'account_code' => '5.1.05.01.01.0001', 'dpa_ref' => 'DPA-'.$o->code.'/2026']);
        }

        // lembaga penerima tambahan
        $pmi = $s->applicantOrg('PMI-KPH', 'PMI Cabang Kepahiang', 'ORMAS');
        $koni = $s->applicantOrg('KONI-KPH', 'KONI Kabupaten Kepahiang', 'BADAN_LEMBAGA');
        $s->user('pemohon.pmi@ehibah.test', 'Ketua PMI Kepahiang', 'PEMOHON', $pmi, 'Ketua');
        $s->user('pemohon.koni@ehibah.test', 'Ketua KONI Kepahiang', 'PEMOHON', $koni, 'Ketua');

        $make = fn (array $o, Status $to) => $s->advance($s->newApplication($o), $to);

        // satu usulan pada tiap tahap utama agar dashboard & antrean terisi
        $make(['title' => 'Pengadaan Alat Peraga Pendidikan Anak Usia Dini', 'amount' => 75_000_000], Status::DRAFT);
        $make(['title' => 'Pelatihan Guru Penggerak Yayasan Cahaya Bangsa', 'amount' => 120_000_000], Status::SUBMITTED);
        $make(['title' => 'Beasiswa Siswa Berprestasi Kurang Mampu', 'amount' => 200_000_000], Status::ADMIN_CHECK);
        $make(['title' => 'Revitalisasi Perpustakaan Yayasan', 'amount' => 90_000_000], Status::TECH_CHECK);
        $make(['title' => 'Pembinaan Atlet Pelajar Cabang Atletik', 'amount' => 150_000_000, 'applicant' => $koni], Status::RECOMMENDED);
        $make(['title' => 'Donor Darah dan Pelatihan Relawan PMI', 'amount' => 60_000_000, 'applicant' => $pmi], Status::TAPD_REVIEW);
        $make(['title' => 'Bantuan Peralatan Laboratorium Komputer', 'amount' => 110_000_000, 'grant_type' => 'HIBAH_BARANG'], Status::APPROVED);
        $make(['title' => 'Penguatan Sarana Olahraga Pelajar', 'amount' => 80_000_000, 'applicant' => $koni], Status::BUDGETED);
        $make(['title' => 'Peningkatan Mutu Sekolah Kejuruan', 'amount' => 250_000_000], Status::NPHD_SIGNED);
        $make(['title' => 'Kegiatan Kepalangmerahan Remaja', 'amount' => 100_000_000, 'applicant' => $pmi], Status::DISBURSED);
        $make(['title' => 'Pengadaan Buku Perpustakaan Sekolah', 'amount' => 60_000_000, 'grant_type' => 'HIBAH_BARANG'], Status::LPJ_REVIEW);
        $make(['title' => 'Pelatihan Kewirausahaan Pemuda', 'amount' => 70_000_000, 'applicant' => $koni], Status::CLOSED);

        // satu usulan ditolak & satu dikembalikan (skenario penolakan / koreksi)
        $a = $make(['title' => 'Usulan Tanpa Kelayakan Teknis', 'amount' => 50_000_000], Status::TECH_CHECK);
        $s->act($a, 'tech_reject', 'verifikator', ['note' => 'Kegiatan tidak sesuai prioritas pembangunan daerah dan RAB tidak wajar.']);
        $b = $make(['title' => 'Usulan Dokumen Kurang', 'amount' => 45_000_000], Status::ADMIN_CHECK);
        $s->act($b, 'admin_return', 'opd', ['note' => 'Surat pengesahan penanggung jawab tidak terbaca. Mohon unggah ulang.']);

        // tunggakan LPJ: usulan tersalur dengan tenggat sudah lewat
        $late = $make(['title' => 'Kegiatan Seni Budaya Daerah', 'amount' => 40_000_000, 'activity_start' => '2026-01-10', 'activity_end' => '2026-02-28'], Status::DISBURSED);
        $late->update(['lpj_due_date' => now()->subDays(12)->toDateString()]);
        app(\App\Services\LpjMonitor::class)->run();
    }
}
