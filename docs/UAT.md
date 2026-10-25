# UAT — Skenario Uji & Kriteria Go-Live

Semua skenario di bawah **sudah otomatis** (`php artisan test`; hasil terakhir pada SQLite **dan** PostgreSQL 16.9: semua lulus).
Lembar ini dipakai untuk UAT oleh pengguna Pemda: jalankan skenario manual di aplikasi (akun demo), lalu isi kolom *Hasil / Paraf*.

## A. 16 skenario Blueprint Bab 12

| No | Skenario | Hasil yang diharapkan | Tes otomatis | Hasil / Paraf |
|---|---|---|---|---|
| 1 | Pengajuan tanpa dokumen lengkap | Ditolak sistem, dokumen kurang ditandai | `test_blueprint_01…` | |
| 2 | Perbaikan dokumen oleh pemohon | Versi baru tersimpan, riwayat tetap ada | `test_blueprint_02…` | |
| 3 | Akses modul di luar kewenangan | Akses ditolak (403) & tercatat di log | `test_blueprint_03…` | |
| 4 | Persetujuan di luar kewenangan | Tombol tidak tersedia / ditolak server | `test_blueprint_04…` | |
| 5 | Usulan tidak direkomendasikan | Tidak bisa masuk tahap penetapan | `test_blueprint_05…` | |
| 6 | Disetujui tanpa alokasi anggaran | Diblokir sampai anggaran tersedia (+ alokasi > pagu ditolak) | `test_blueprint_06…`, `06b` | |
| 7 | NPHD belum lengkap | Penyaluran tidak dapat diajukan | `test_blueprint_07…` | |
| 8 | Penyaluran melebihi nilai NPHD | Ditolak validasi server | `test_blueprint_08…` | |
| 9 | Permintaan pembayaran ganda | Hanya satu transaksi diproses | `test_blueprint_09…` | |
| 10 | Hibah barang lewat jalur uang | Jalur tidak tersedia | `test_blueprint_10…` | |
| 11 | LPJ melewati batas waktu | Status tunggakan & notifikasi (+ pengingat, surat peringatan berjenjang) | `test_blueprint_11…`, `11b` | |
| 12 | LPJ tidak lengkap | Dikembalikan dengan catatan | `test_blueprint_12…` | |
| 13 | Pengembalian sisa dana | Bukti setoran tercatat & terekap; penutupan diblokir sebelum disetor | `test_blueprint_13…` | |
| 14 | Upaya mengubah log audit | Tidak mungkin (model, trigger DB, rantai hash) | `test_blueprint_14…`, `14b` | |
| 15 | Backup dan pemulihan | Data pulih utuh | `test_blueprint_15…` + runbook OPERASIONAL §5 | |
| 16 | Akses dari perangkat seluler | Tampilan & fungsi berjalan baik | `test_blueprint_16…` + uji manual di ponsel | |

## B. 16 uji Matriks Kepatuhan (UAT-001…016)

| ID | Rujukan | Skenario | Tes otomatis |
|---|---|---|---|
| UAT-001 | Pasal 2 | Peruntukan spesifik kosong → rekomendasi ditahan | `test_matriks_uat001…` |
| UAT-002 | Pasal 4–5 | Kategori badan/lembaga tanpa dokumen legalitas → checklist tidak lengkap; dokumen berjangka wajib tanggal berlaku | `…uat002…` |
| UAT-003 | Pasal 6 & Lamp. A | Proposal lengkap → rekomendasi (PDF) memuat identitas & nilai yang benar | `…uat003…` |
| UAT-004 | Pasal 7 | Penetapan tanpa keputusan/APBD ditolak | `…uat004…` |
| UAT-005 | Pasal 8 & Lamp. B | Penyaluran tanpa NPHD diblokir | `…uat005…` |
| UAT-006 | Pasal 9 | Rp100.000.000 → tahap I Rp70.000.000, tahap II Rp30.000.000 | `…uat006…` |
| UAT-007 | Pasal 9 ayat 3 | Pengecualian 100% butuh alasan + otorisasi; tidak otomatis | `…uat007…`, `007b` |
| UAT-008 | Pasal 9 | Tahap II terkunci sebelum laporan tahap I diterima | `…uat008…` |
| UAT-009 | Pasal 13–14 | Hibah barang tanpa BAST tidak dapat dinyatakan selesai | `…uat009…` |
| UAT-010 | Pasal 16 & Lamp. E | LPJ uang kurang bukti → perlu perbaikan, kekurangan tercatat | `…uat010…` |
| UAT-011 | Pasal 19 | Tenggat LPJ 1 bulan, dibatasi 10 Januari; penafsiran dikonfirmasi Bagian Hukum | `…uat011…` |
| UAT-012 | Pasal 20 | Hibah 100 jt, realisasi 95 jt → sisa 5 jt + bukti setor | `…uat012…` |
| UAT-013 | Pasal 21 | Monev → ke Bupati, tembusan Inspektorat | `…uat013…` |
| UAT-014 | Pasal 10–12 | Alur khusus parpol (checklist, permohonan, BA verifikasi, ketua+sekretaris) | `…uat014…` |
| UAT-015 | Pasal 22–44 | Bansos tidak masuk menu/workflow hibah | `…uat015…` |
| UAT-016 | Pasal 45–46 | Perbup 18/2014 & 62/2019 berstatus dicabut, tidak bisa jadi dasar aktif | `…uat016…` |

## C. Uji tambahan (di luar daftar Blueprint)

| Area | Tes |
|---|---|
| Siklus penuh | `FullCycleTest` — hibah uang (DRAFT→CLOSED, 2 tahap, sisa dana) & hibah barang (BAST, aset) |
| Hak akses | `test_setiap_aksi_workflow_selaras_dengan_matriks_hak_akses`, `test_matriks_sesuai_blueprint` |
| Cakupan data | `PagesSmokeTest` — pemohon/OPD lain & Admin tidak dapat membuka usulan/dokumen |
| Halaman & laporan | `PagesSmokeTest` — seluruh halaman 8 peran; 10 laporan × (HTML, Excel, PDF); cetak Lampiran A/B/C/D, berita acara, surat peringatan |
| Keamanan | login (kunci akun, pesan seragam), registrasi→validasi, sandi lemah, TOTP RFC 6238, MFA wajib, header HTTP, berkas palsu/besar/terlarang, berkas privat |
| Konkurensi | `lock_version`; aksi pada status salah ditolak |
| Penolakan | alasan wajib, pagu kembali, notifikasi, status akhir tanpa aksi |
| Koreksi data | alasan, 4-mata, whitelist, konflik perubahan, nilai uang tidak dapat dikoreksi |

## D. Kriteria Go-Live (Blueprint Bab 12)

| ✔ | Kriteria | Status di rilis ini |
|---|---|---|
| ☐ | Regulasi & SOP disetujui | **Menunggu Pemda** — lihat KEPUTUSAN-TERBUKA |
| ☐ | Semua jenis hibah tervalidasi | Uang & barang diuji; kategori parpol diuji dasar; validasi hukum menunggu |
| ☑ | Tidak ada cacat kritis | seluruh tes lulus (SQLite & PostgreSQL) |
| ☑ | Hak akses & konfigurasi diuji | matriks + tes akses + tes konsistensi |
| ☑ | Backup & pemulihan berhasil | diuji pada PostgreSQL (OPERASIONAL §5); ulangi di server produksi |
| ☐ | Berita acara UAT ditandatangani | lembar A–B di atas |
| ☑ | Dokumentasi lengkap | `docs/` |
| ☐ | Persetujuan pimpinan | — |
