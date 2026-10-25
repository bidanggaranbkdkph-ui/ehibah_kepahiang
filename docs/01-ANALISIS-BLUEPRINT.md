# 1 · Analisis Blueprint E-Hibah Kepahiang

Sumber yang dianalisis (folder `E HIBAH`): `Blueprint_E-Hibah_Kepahiang.pdf` (12 halaman, terbaca lewat berkas HTML sumbernya
`blueprint.html`), tiga infografis PNG, dan `Matriks_Kepatuhan_EBibah_Kepahiang.xlsx` (46 pasal Perbup, Lampiran A–G, 16 skenario UAT).

## 1.1 Ringkasan isi blueprint

| Bab | Isi | Diwujudkan di aplikasi |
|---|---|---|
| 1 | Latar belakang, tujuan (transparansi, efisiensi, kepatuhan, data akurat), lingkup: **semua hibah APBD**, uang & barang; **bansos di luar lingkup** | `grant_types.scope`, bansos dinonaktifkan & diblokir server |
| 2 | Inventarisasi regulasi + prinsip "regulasi → kontrol sistem" | tabel `regulations` (status berlaku), [matriks kepatuhan](02-MATRIKS-KEPATUHAN.md) |
| 3 | Arsitektur 6 lapis; 7 peran; layanan bersama (autentikasi, workflow, notifikasi, dokumen, audit) | `app/Services/*`, middleware, `config/ehibah.php` |
| 4 | **15 SOP**, tiap SOP 1 penanggung jawab + 1 keluaran | [05-WORKFLOW-SOP](05-WORKFLOW-SOP.md) |
| 5 | BPMN hibah uang (10 langkah) vs hibah barang (BAST, pencatatan aset) | dua jalur terpisah yang dipilih otomatis berdasarkan `grant_types.form` |
| 6 | Matriks hak akses 7 peran × 12 fitur (F/P/V/-) | `config/ehibah.php → permissions`, [04-HAK-AKSES](04-HAK-AKSES.md) |
| 7 | 18 tabel inti, `grant_applications` sebagai pusat | 36 tabel (superset), [03-DATABASE](03-DATABASE.md) |
| 8 | 12 status + 3 pengecualian (RETURNED/REJECTED/CANCELLED) | enum `Status` + `WorkflowService` |
| 9 | Dashboard (usulan, nilai, realisasi, tunggakan LPJ) + **10 laporan** | `DashboardController`, `ReportService` (Excel/PDF) |
| 10 | 15–24 minggu, 5 tahap | [OPERASIONAL](OPERASIONAL.md), [UAT](UAT.md) |
| 11 | Keamanan minimum: hash+MFA, audit trail tak dapat diubah, keamanan berkas, integritas transaksi, backup, TTE, integrasi API | semuanya diimplementasikan kecuali integrasi eksternal (lihat 1.4) |
| 12 | 16 skenario UAT + kriteria go-live | 16 + 16 tes otomatis ([UAT](UAT.md)) |
| 13 | 6 keputusan sebelum coding | [KEPUTUSAN-TERBUKA](KEPUTUSAN-TERBUKA.md) |

## 1.2 Temuan ketidakkonsistenan (perlu konfirmasi Pemda)

| # | Temuan | Perlakuan di aplikasi |
|---|---|---|
| 1 | PDF Bab 2 menulis **Perbup Kepahiang No. 25 Tahun 2025**, sedangkan Matriks Kepatuhan (salinan resmi BPK) menyebut **No. 25 Tahun 2023**, ditetapkan 18 Des 2023. Infografis ke-1 menulis "No. … Tahun 2024". | Di-seed sebagai **Perbup 25/2023** (sumber primer). Catatan peringatan tersimpan pada data regulasi. |
| 2 | Bab 2 baris 6 dan 7: dua keputusan Sekda berbeda fungsi memakai nomor yang sama (900-43/2023). | Di-seed satu entri berstatus `PERLU_DICEK`. |
| 3 | Perda 6/2021 dan Permendagri 99/2019 ditandai "perlu dicek" oleh blueprint; infografis memberi judul Permendagri 99/2019 yang berbeda-beda. | Status `PERLU_DICEK`, tidak dapat dipilih sebagai dasar hukum usulan. |
| 4 | Infografis menamai aplikasi "E-**Bibah**" (typo) dan memakai daftar 6–7 peran yang berbeda dari PDF (mis. "Admin OPD", "Tim Teknis", tanpa TAPD/Inspektorat). | Mengikuti PDF sebagai dokumen formal: **7 peran + Admin Sistem**. |
| 5 | Matriks hak akses PDF tidak memuat kolom **Admin Sistem**, padahal catatannya menyebut Admin dikelola terpisah "tanpa akses mengubah data permohonan". | Kolom Admin ditambahkan: hanya akun/master/konfigurasi/backup/log sistem; tanpa akses data permohonan (diuji). |
| 6 | Blueprint menyebut 18 tabel; Matriks Kepatuhan menuntut hal yang tidak punya tabel (pagu, tahap 70/30, surat peringatan, koreksi, versi dokumen, dokumen wajib kondisional). | Skema diperluas menjadi 36 tabel; semua tabel blueprint tetap ada dengan nama yang sama. |
| 7 | Perbup Pasal 19: "1 bulan setelah kegiatan selesai **atau** paling lambat 10 Januari tahun berikutnya" — ambigu (mana yang berlaku?). | Dihitung `min(selesai + 1 bulan, 10 Jan tahun anggaran+1)`; dapat dimatikan lewat Konfigurasi (`lpj.cap_enabled`). **Perlu keputusan Bagian Hukum** (UAT-011). |
| 8 | Status flow di blueprint tidak membedakan "ditetapkan" vs "anggaran terkonfirmasi" dan UAT-06 mensyaratkan blokir tanpa alokasi. | `TAPD_REVIEW` mencadangkan pagu → Bupati menetapkan (wajib ada alokasi + Keputusan) → `BUDGETED` setelah BPKAD mengonfirmasi DPA. |

## 1.3 Keputusan desain utama

1. **Satu mesin workflow di sisi server** (`WorkflowService`): semua perpindahan status melalui satu pintu; UI hanyalah cerminan `availableActions()`.
   Tombol tersembunyi bukan kontrol — server menolak dan mencatat percobaan akses (UAT 3/4).
2. **Guard = regulasi dalam kode.** Setiap prasyarat (dokumen wajib, kriteria Pasal 2, alokasi anggaran, NPHD, urutan tahap 70/30, batas nilai NPHD,
   BAST, bukti setor sisa dana, monev) adalah fungsi `guardXxx` yang mengembalikan daftar alasan; alasan itu ditampilkan ke pengguna di panel tindakan.
3. **Uang = bilangan bulat rupiah (`bigint`)**; pembagian 70/30 memasukkan selisih pembulatan ke tahap II sehingga total persis.
4. **Anti pembayaran ganda berlapis**: `idempotency_key` unik, pengecekan tahap aktif, penguncian baris (`FOR UPDATE`), indeks unik parsial PostgreSQL, dan validasi total ≤ NPHD.
5. **Audit trail append-only berantai-hash**: model menolak ubah/hapus, trigger DB menolak `UPDATE/DELETE/TRUNCATE`, dan `prev_hash → hash` mendeteksi manipulasi langsung ke tabel (`audit:verify`).
6. **Cakupan data per baris** (`GrantApplication::visibleTo`) — satu sumber kebenaran untuk daftar, detail, laporan, dan unduhan dokumen.
7. **Koreksi data** hanya lewat permintaan → persetujuan pihak lain (4-mata) → penerapan; nilai uang tidak boleh dikoreksi (batalkan & ajukan ulang).
8. **Tanpa build-step**: CSS/JS murni dilayani dari origin sendiri sehingga CSP ketat (`default-src 'self'`) dan penerapan di jaringan pemerintah sederhana.

## 1.4 Di luar cakupan rilis ini (disiapkan titik pasangnya)

| Item | Status | Titik integrasi yang sudah ada |
|---|---|---|
| SIPD RI / sistem keuangan daerah (sinkron anggaran) | belum — butuh spesifikasi API resmi | `budget_ceilings`, `budget_allocations.synced_at`, `dpa_ref` |
| Tanda tangan elektronik BSrE | belum — butuh kredensial/kontrak | `nphd_documents.signature_method/signature_ref` |
| SSO Pemda | belum | middleware `auth` dapat diganti guard SSO |
| Pemindaian antivirus | **ada** (ClamAV opsional) | `EHIBAH_ANTIVIRUS=clamscan` |
| Modul bansos | **sengaja tidak ada** (Pasal 22–44) | `grant_types.scope = BANSOS` diblokir |

Blueprint sendiri menetapkan integrasi sebagai "bertahap" dan "hanya melalui API resmi dengan izin" (Bab 3 & 11), sehingga tidak diimplementasikan tanpa spesifikasi.
