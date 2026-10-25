# 4 · Matriks Hak Akses

Sumber tunggal: `config/ehibah.php → permissions` (dibaca oleh `App\Support\Permission`). Tabel di bawah dihasilkan oleh
`php artisan ehibah:matrix` sehingga selalu sama dengan perilaku aplikasi. Halaman yang sama tersedia di menu **Matriks Hak Akses**.

**F** = kelola penuh (buat, ubah, setujui) · **P** = proses/verifikasi · **V** = lihat saja · **–** = tidak ada akses

| Modul / fitur | Pemohon (Penerima Hibah) | OPD Teknis | Tim Verifikasi | TAPD | Bupati/Pejabat Berwenang | BPKAD/BUD | Inspektorat | Admin Sistem |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Umum** | | | | | | | | |
| Akun & profil sendiri | **F** | **F** | **F** | **F** | **F** | **F** | **F** | **F** |
| **Pengajuan & penilaian** | | | | | | | | |
| Pengajuan usulan | **F** | V | V | – | – | – | V | – |
| Verifikasi administrasi | – | **P** | V | – | – | – | V | – |
| Verifikasi teknis | – | V | **P** | – | – | – | V | – |
| Rekomendasi | – | **F** | V | V | – | – | V | – |
| **Penetapan & anggaran** | | | | | | | | |
| Penetapan penerima | – | V | – | **P** | **F** | V | V | – |
| Anggaran, pagu & alokasi | – | **P** | – | **F** | – | **F** | V | – |
| NPHD | **P** | **F** | – | – | V | V | V | – |
| **Penyaluran & pertanggungjawaban** | | | | | | | | |
| Penyaluran (uang / barang) | V | **P** | – | – | – | **F** | V | – |
| Laporan / LPJ | **F** | **P** | – | – | V | V | V | – |
| Monitoring & evaluasi | – | **F** | – | V | V | V | **P** | – |
| Koreksi data | **P** | **P** | **P** | – | – | **P** | V | – |
| **Pengawasan** | | | | | | | | |
| Dasbor & laporan | – | V | V | V | V | V | V | – |
| Audit trail proses | – | – | – | – | – | V | **F** | – |
| **Administrasi sistem** | | | | | | | | |
| Kelola pengguna & validasi akun | – | – | – | – | – | – | – | **F** |
| Data master (OPD, tahun, jenis, dokumen, regulasi) | – | – | – | V | – | V | – | **F** |
| Konfigurasi sistem | – | – | – | – | – | – | – | **F** |
| Backup & pemulihan | – | – | – | – | – | – | – | **F** |
| Log sistem & keamanan | – | – | – | – | – | – | V | **F** |

## Cakupan data (row-level)

Hak pada tabel di atas menentukan *fitur*; cakupan *baris* ditentukan `GrantApplication::visibleTo(user)`:

| Peran | Usulan yang terlihat |
|---|---|
| Pemohon | hanya usulan lembaganya sendiri (semua status) |
| OPD Teknis | usulan di OPD-nya yang sudah diajukan |
| Tim Verifikasi | lintas OPD, sejak usulan memasuki verifikasi administrasi |
| TAPD, Bupati | sejak usulan direkomendasikan |
| BPKAD | sejak usulan memasuki pertimbangan TAPD (alokasi & penyaluran) |
| Inspektorat | semua usulan yang telah diajukan (pengawasan) |
| Admin Sistem | **tidak ada** — hanya akun, master, konfigurasi, backup, log sistem |

Kewenangan **tindakan** tidak hanya peran, tetapi juga organisasi: Pemohon hanya untuk usulan lembaganya; OPD hanya untuk usulan OPD-nya
(mis. OPD Kesehatan tidak dapat memverifikasi usulan Dinas Pendidikan). Ditegakkan di `WorkflowService::isAuthorized` dan diuji.

## Aturan pemisahan tugas

| Aturan | Penegakan |
|---|---|
| Admin Sistem tidak boleh merangkap peran proses; Pemohon tidak boleh merangkap peran petugas | `Admin\UserController::guardRoles` |
| Pengaju koreksi tidak boleh menyetujui koreksinya (4-mata) | `CorrectionService::canDecide` |
| Hanya BPKAD yang mengotorisasi pencairan 100% (OPD hanya mengajukan) | `DisbursementService::authorizeFullException` |
| NPHD hanya ditandatangani Kepala SKPD yang berwenang atas urusan | `guardSignNphd` |
| Penerima tidak dapat memeriksa/menyetujui dokumennya sendiri | pemeriksaan dokumen hanya OPD/BPKAD |
| Admin tidak dapat melihat jejak proses (hanya kategori SISTEM/AKSES) | `AuditController::categories` |

## Penolakan akses

Setiap penolakan (URL langsung, aksi API, unduhan dokumen) menghasilkan HTTP 403 dan baris `ACCESS_DENIED` di audit trail kategori *AKSES*
(pengguna, jalur, alasan, IP). Tombol yang tidak berwenang tidak ditampilkan, tetapi **server tetap menolak** bila dipaksa.