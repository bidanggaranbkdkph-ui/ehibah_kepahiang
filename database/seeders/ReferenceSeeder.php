<?php

namespace Database\Seeders;

use App\Models\DocumentRequirement;
use App\Models\FiscalYear;
use App\Models\GrantType;
use App\Models\RecipientCategory;
use App\Models\Regulation;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Data referensi wajib (aman untuk produksi): peran, kategori penerima, jenis hibah,
 * regulasi, dokumen wajib (Matriks Kepatuhan), tahun anggaran.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('ehibah.roles') as $code => $name) {
            Role::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $cats = [
            ['PEMERINTAH', 'Pemerintah pusat / pemerintah daerah lain', 'Pasal 3-4', false, false, 'Satuan kerja pusat di wilayah Kepahiang; dilarang tumpang tindih pendanaan APBN; ketentuan daerah otonom baru.'],
            ['BUMN_BUMD', 'BUMN / BUMD', 'Pasal 3-4', false, true, 'Dokumen pendirian dan pengesahan; ketentuan khusus BUMN/BUMD.'],
            ['BADAN_LEMBAGA', 'Badan / lembaga berbadan hukum Indonesia', 'Pasal 3-5', false, true, 'Wajib legalitas, domisili di Kepahiang, kepengurusan.'],
            ['ORMAS', 'Organisasi kemasyarakatan berbadan hukum', 'Pasal 3-5', false, true, 'Wajib pengesahan badan hukum/pendaftaran sesuai ketentuan.'],
            ['PARPOL', 'Partai politik (bantuan keuangan)', 'Pasal 10-12, 18', true, true, 'Alur khusus: DPA-SKPD, Keputusan Bupati, SPP/SPM/SP2D, LPJ setelah pemeriksaan BPK.'],
        ];
        foreach ($cats as [$code, $name, $basis, $parpol, $legal, $rules]) {
            RecipientCategory::updateOrCreate(['code' => $code], [
                'name' => $name, 'legal_basis' => $basis, 'is_parpol' => $parpol, 'requires_legal_entity' => $legal, 'special_rules' => $rules,
            ]);
        }

        GrantType::updateOrCreate(['code' => 'HIBAH_UANG'], ['name' => 'Hibah Uang', 'form' => 'UANG', 'scope' => 'HIBAH']);
        GrantType::updateOrCreate(['code' => 'HIBAH_BARANG'], ['name' => 'Hibah Barang / Jasa', 'form' => 'BARANG', 'scope' => 'HIBAH']);
        // Bansos berada di luar workflow hibah (Matriks Kepatuhan Pasal 22-44): dinonaktifkan sengaja
        GrantType::updateOrCreate(['code' => 'BANSOS'], [
            'name' => 'Bantuan Sosial (di luar cakupan E-Hibah)', 'form' => 'UANG', 'scope' => 'BANSOS', 'is_active' => false,
            'requirements_note' => 'Memiliki regulasi dan SOP tersendiri; tidak boleh masuk rekap hibah.',
        ]);

        $this->regulations();
        $this->requirements();

        FiscalYear::updateOrCreate(['year' => 2025], ['status' => 'TUTUP']);
        FiscalYear::updateOrCreate(['year' => 2026], ['status' => 'BERJALAN', 'submission_open' => '2026-01-01', 'submission_close' => '2026-12-31']);
        FiscalYear::updateOrCreate(['year' => 2027], ['status' => 'RENCANA']);
    }

    private function regulations(): void
    {
        $perbup25 = Regulation::updateOrCreate(['code' => 'PERBUP-KPH-25-2023'], [
            'title' => 'Peraturan Bupati Kepahiang Nomor 25 Tahun 2023 tentang Hibah dan Bantuan Sosial',
            'type' => 'PERBUP', 'status' => 'AKTIF', 'is_grant_basis' => true,
            'enacted_at' => '2023-12-18', 'effective_at' => '2023-12-18',
            'notes' => 'Acuan lokal utama. CATATAN: dokumen Blueprint menulis "Tahun 2025" pada satu tempat; salinan PDF resmi BPK memuat Tahun 2023 — Bagian Hukum perlu mengonfirmasi.',
        ]);
        foreach ([['PERBUP-KPH-18-2014', 'Peraturan Bupati Kepahiang Nomor 18 Tahun 2014 (Hibah dan Bansos)'], ['PERBUP-KPH-62-2019', 'Peraturan Bupati Kepahiang Nomor 62 Tahun 2019 (Perubahan Kedua Perbup 18/2014)']] as [$code, $title]) {
            Regulation::updateOrCreate(['code' => $code], [
                'title' => $title, 'type' => 'PERBUP', 'status' => 'DICABUT', 'is_grant_basis' => true,
                'revoked_by_id' => $perbup25->id, 'revoked_at' => '2023-12-18', 'notes' => 'Dicabut oleh Perbup 25/2023 (Pasal 45).',
            ]);
        }
        $others = [
            ['UU-23-2014', 'Undang-Undang Nomor 23 Tahun 2014 tentang Pemerintahan Daerah', 'UU', 'AKTIF'],
            ['UU-17-2003', 'Undang-Undang Nomor 17 Tahun 2003 tentang Keuangan Negara', 'UU', 'AKTIF'],
            ['UU-1-2022', 'Undang-Undang Nomor 1 Tahun 2022 tentang Hubungan Keuangan antara Pemerintah Pusat dan Pemerintah Daerah', 'UU', 'AKTIF'],
            ['PP-12-2019', 'Peraturan Pemerintah Nomor 12 Tahun 2019 tentang Pengelolaan Keuangan Daerah', 'PP', 'AKTIF'],
            ['PERMENDAGRI-77-2020', 'Permendagri Nomor 77 Tahun 2020 tentang Pedoman Teknis Pengelolaan Keuangan Daerah', 'PERMENDAGRI', 'AKTIF'],
            ['PERMENDAGRI-99-2019', 'Permendagri Nomor 99 Tahun 2019 (tercantum pada Blueprint sebagai pedoman hibah daerah)', 'PERMENDAGRI', 'PERLU_DICEK'],
            ['PERDA-KPH-6-2021', 'Perda Kabupaten Kepahiang Nomor 6 Tahun 2021 (pengelolaan keuangan daerah)', 'PERDA', 'PERLU_DICEK'],
            ['PERBUP-KPH-28-2022', 'Peraturan Bupati Kepahiang Nomor 28 Tahun 2022 (sistem & prosedur pengelolaan keuangan daerah)', 'PERBUP', 'AKTIF'],
            ['KEP-SEKDA-900-43-2023', 'Keputusan Sekda Nomor 900-43/2023 tentang Tim Verifikasi Hibah', 'KEPUTUSAN', 'PERLU_DICEK'],
        ];
        foreach ($others as [$code, $title, $type, $status]) {
            Regulation::updateOrCreate(['code' => $code], [
                'title' => $title, 'type' => $type, 'status' => $status, 'is_grant_basis' => false,
                'notes' => $status === 'PERLU_DICEK' ? 'Nomor/status perlu dikonfirmasi Bagian Hukum (Blueprint Bab 2).' : null,
            ]);
        }
    }

    /** Dokumen wajib per tahap — turunan Perbup Pasal 5-6, 9, 11-12, 14, 16, 18 dan Lampiran A-E. */
    private function requirements(): void
    {
        $cat = RecipientCategory::pluck('id', 'code');
        $all = ['PEMERINTAH', 'BUMN_BUMD', 'BADAN_LEMBAGA', 'ORMAS', 'PARPOL'];
        $nonGov = ['BUMN_BUMD', 'BADAN_LEMBAGA', 'ORMAS', 'PARPOL'];

        // [code, name, stage, form, categories(null=semua), mandatory, validity, template, sort]
        $rows = [
            ['SURAT_PERMOHONAN', 'Surat permohonan hibah kepada Bupati', 'PENGAJUAN', null, null, true, false, null, 1],
            ['PROPOSAL', 'Proposal (latar belakang, maksud/tujuan, sasaran, program/kegiatan)', 'PENGAJUAN', null, null, true, false, null, 2],
            ['RAB', 'Rencana Anggaran Biaya (RAB) / daftar kebutuhan barang', 'PENGAJUAN', null, null, true, false, null, 3],
            ['PANITIA', 'Susunan organisasi / panitia pelaksana', 'PENGAJUAN', null, null, true, false, null, 4],
            ['PENGESAHAN_PJ', 'Surat pengesahan penanggung jawab', 'PENGAJUAN', null, null, true, false, null, 5],
            ['KTP_PJ', 'KTP penanggung jawab', 'PENGAJUAN', null, null, true, false, null, 6],
            ['REKENING', 'Rekening bank atas nama lembaga (halaman depan buku/rekening koran)', 'PENGAJUAN', 'UANG', null, true, false, null, 7],
            ['NPWP', 'NPWP lembaga', 'PENGAJUAN', null, $nonGov, true, false, null, 8],
            ['AKTA_BADAN_HUKUM', 'Akta pendirian & pengesahan badan hukum', 'PENGAJUAN', null, ['BADAN_LEMBAGA', 'ORMAS'], true, false, null, 9],
            ['SK_KEPENGURUSAN', 'SK kepengurusan yang masih berlaku', 'PENGAJUAN', null, ['BADAN_LEMBAGA', 'ORMAS', 'PARPOL'], true, true, null, 10],
            ['DOMISILI', 'Surat keterangan domisili di Kabupaten Kepahiang', 'PENGAJUAN', null, ['BADAN_LEMBAGA', 'ORMAS'], true, true, null, 11],
            ['DOK_PENDIRIAN_BUMN', 'Dokumen pendirian & pengesahan BUMN/BUMD', 'PENGAJUAN', null, ['BUMN_BUMD'], true, false, null, 12],
            ['PERNYATAAN_NON_APBN', 'Pernyataan tidak tumpang tindih pendanaan APBN', 'PENGAJUAN', null, ['PEMERINTAH'], true, false, null, 13],
            ['SURAT_SATKER', 'Keterangan lokasi satuan kerja di wilayah Kepahiang', 'PENGAJUAN', null, ['PEMERINTAH'], true, false, null, 14],
            ['AD_ART', 'AD/ART partai politik', 'PENGAJUAN', null, ['PARPOL'], true, false, null, 15],
            ['REKOM_KESBANGPOL', 'Rekomendasi/verifikasi Kesbangpol', 'PENGAJUAN', null, ['PARPOL'], true, false, null, 16],

            ['NPHD_TTD', 'NPHD bertanda tangan kedua pihak', 'NPHD', null, null, true, false, 'Lampiran B.1 / B.2', 1],

            ['PAKTA_C1', 'Pakta integritas penerima hibah uang', 'PENCAIRAN', 'UANG', null, true, false, 'Lampiran C.1', 1],
            ['SURAT_PERMOHONAN_CAIR', 'Surat permohonan pencairan', 'PENCAIRAN', 'UANG', null, true, false, null, 2],
            ['KUITANSI', 'Kuitansi bermeterai', 'PENCAIRAN', 'UANG', null, true, false, null, 3],
            ['SURAT_TANGGUNG_JAWAB_D', 'Surat pernyataan tanggung jawab penerima', 'PENCAIRAN', 'UANG', null, true, false, 'Lampiran D', 4],
            ['PERMOHONAN_PARPOL', 'Permohonan pencairan ditandatangani ketua & sekretaris (kop/stempel)', 'PENCAIRAN', 'UANG', ['PARPOL'], true, false, null, 5],
            ['BA_VERIF_PARPOL', 'Berita acara verifikasi administrasi (Pasal 12)', 'PENCAIRAN', 'UANG', ['PARPOL'], true, false, null, 6],

            ['PAKTA_C2', 'Pakta integritas penerima hibah barang', 'PENYERAHAN', 'BARANG', null, true, false, 'Lampiran C.2', 1],
            ['KESEDIAAN_MENERIMA', 'Surat kesediaan menerima barang', 'PENYERAHAN', 'BARANG', null, true, false, null, 2],
            ['IDENTITAS_PJ', 'Identitas penanggung jawab penerima barang', 'PENYERAHAN', 'BARANG', null, true, false, null, 3],
            ['BAST', 'Berita Acara Serah Terima (BAST) bertanda tangan', 'PENYERAHAN', 'BARANG', null, true, false, 'Pasal 14', 4],

            ['LAPORAN_E', 'Laporan penggunaan hibah (sampul s.d. lampiran)', 'LPJ', null, null, true, false, 'Lampiran E.1-E.4', 1],
            ['BUKTI_PENGELUARAN', 'Bukti pengeluaran yang sah', 'LPJ', 'UANG', null, true, false, null, 2],
            ['REKENING_KORAN', 'Rekening koran penerima', 'LPJ', 'UANG', null, true, false, null, 3],
            ['DOKUMENTASI', 'Dokumentasi kegiatan / foto penyerahan', 'LPJ', null, null, true, false, null, 4],
            ['BUKTI_SETOR', 'Bukti setor sisa dana ke kas daerah', 'LPJ', 'UANG', null, false, false, 'Pasal 20', 5],
            ['HASIL_BPK', 'Hasil pemeriksaan BPK (partai politik)', 'LPJ', 'UANG', ['PARPOL'], false, false, 'Pasal 18', 6],
        ];

        foreach ($rows as [$code, $name, $stage, $form, $cats, $mandatory, $validity, $tpl, $sort]) {
            foreach ($cats ?? [null] as $c) {
                DocumentRequirement::updateOrCreate(
                    ['code' => $code, 'recipient_category_id' => $c ? $cat[$c] : null],
                    ['name' => $name, 'stage' => $stage, 'grant_form' => $form, 'is_mandatory' => $mandatory,
                        'requires_validity' => $validity, 'template_ref' => $tpl, 'sort_order' => $sort]
                );
            }
        }
    }
}
