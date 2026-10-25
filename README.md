# E-Hibah Kabupaten Kepahiang

Aplikasi web pengelolaan **hibah APBD** Pemerintah Kabupaten Kepahiang — dari usulan, verifikasi, rekomendasi,
penetapan, NPHD, penyaluran (uang/barang), LPJ, sampai monitoring & evaluasi — dibangun dari
*Blueprint Awal Aplikasi E-Hibah v1.0* dan *Matriks Kepatuhan Perbup Kepahiang tentang Hibah*.

| | |
|---|---|
| Backend & frontend | **Laravel 13** (PHP 8.3), Blade, CSS/JS tanpa build-step & tanpa CDN |
| Database | **PostgreSQL 16** (diuji juga pada SQLite untuk tes cepat) |
| Laporan | Excel (PhpSpreadsheet) & PDF (DomPDF) |
| Keamanan | Role-based + row-level, audit trail append-only berantai-hash, MFA TOTP, unggahan privat |

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [docs/01-ANALISIS-BLUEPRINT.md](docs/01-ANALISIS-BLUEPRINT.md) | Analisis blueprint, temuan ketidakkonsistenan, keputusan desain |
| [docs/02-MATRIKS-KEPATUHAN.md](docs/02-MATRIKS-KEPATUHAN.md) | Pasal Perbup → fitur → kontrol di kode → tes |
| [docs/03-DATABASE.md](docs/03-DATABASE.md) | Struktur database (ERD, kamus data, indeks & trigger) |
| [docs/04-HAK-AKSES.md](docs/04-HAK-AKSES.md) | Matriks hak akses peran × modul + cakupan data |
| [docs/05-WORKFLOW-SOP.md](docs/05-WORKFLOW-SOP.md) | Status, transisi, guard, skenario penolakan & koreksi data |
| [docs/06-UIUX.md](docs/06-UIUX.md) | Sistem desain, peta halaman, wireframe, prototipe mobile |
| [docs/07-PERHITUNGAN-DOKUMEN-WAJIB.md](docs/07-PERHITUNGAN-DOKUMEN-WAJIB.md) | Rumus (70/30, tenggat LPJ, sisa dana, pagu) dan daftar dokumen wajib |
| [docs/OPERASIONAL.md](docs/OPERASIONAL.md) | Instalasi produksi, keamanan, backup & pemulihan, pemantauan |
| [docs/UAT.md](docs/UAT.md) | 16 skenario UAT Blueprint + 16 uji Matriks Kepatuhan ↔ tes otomatis |
| [docs/KEPUTUSAN-TERBUKA.md](docs/KEPUTUSAN-TERBUKA.md) | Hal yang harus diputuskan/divalidasi Pemda sebelum go-live |

Prototipe mobile klik-able: `public/prototype/mobile.html` (buka lewat `/prototype/mobile.html`).

## Mulai cepat (pengembangan)

Prasyarat: PHP ≥ 8.3 (ekstensi `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd`, `intl`), Composer, PostgreSQL ≥ 14.

```bash
composer install
cp .env.example .env            # sesuaikan DB_* dan APP_ENV=local
php artisan key:generate
createdb ehibah                 # atau lewat psql; buat user & kata sandi
php artisan migrate --seed      # ReferenceSeeder + DemoSeeder (non-produksi)
php artisan serve               # http://localhost:8000
```

**Windows tanpa hak admin:** ekstrak PostgreSQL *binaries* (zip EDB) ke `%USERPROFILE%\ehibah-pg\pgsql`, jalankan `initdb`, buat role/DB `ehibah`,
lalu `powershell -ExecutionPolicy Bypass -File scripts\dev-windows.ps1` (menyalakan PostgreSQL + aplikasi; `-Stop` untuk mematikan).

Akun demo (hanya `APP_ENV=local`; kata sandi dari `DEMO_PASSWORD`, bawaan `EHibah#Demo2026`):
`pemohon@`, `opd@`, `kadis@` (Kepala Dinas — penandatangan NPHD), `verifikator@`, `tapd@`, `bupati@`, `bpkad@`,
`inspektorat@`, `admin@` + `ehibah.test`.

Produksi: jalankan **hanya** `php artisan migrate --force --seed --class=ReferenceSeeder` (data referensi),
lalu buat akun Admin Sistem pertama — lihat [docs/OPERASIONAL.md](docs/OPERASIONAL.md).

## Perintah artisan penting

| Perintah | Fungsi |
|---|---|
| `php artisan ehibah:lpj-reminders` | Pengingat LPJ, penandaan tunggakan, surat peringatan (dijadwalkan 06:30) |
| `php artisan ehibah:backup {DB\|FILES\|FULL} --verify` | Backup + uji keutuhan (dijadwalkan harian/mingguan) |
| `php artisan audit:verify` | Verifikasi rantai hash audit trail |
| `php artisan schedule:work` / cron `* * * * * php artisan schedule:run` | Menjalankan penjadwal |
| `php artisan test` | 61 tes otomatis (workflow, UAT, keamanan, halaman, laporan) — lulus di SQLite & PostgreSQL 16 |
| `php artisan ehibah:create-admin <email> "<nama>"` | Admin Sistem pertama (produksi) |
| `php artisan ehibah:matrix` | Cetak matriks hak akses sebagai Markdown |

## Struktur kode

```
app/Services/        WorkflowService (alur status+guard) · ProcessService · DisbursementService · GoodsService
                     AccountabilityService (LPJ/monev/sisa dana) · CorrectionService · BudgetService
                     DocumentService/Checklist · AuditService · ReportService · BackupService · LpjMonitor
app/Support/         Permission (matriks) · Setting · Fmt · Totp · ScenarioBuilder (data uji/demo via layanan nyata)
config/ehibah.php    Peran, matriks hak akses, nilai bawaan aturan bisnis, bidang koreksi
database/migrations  36 tabel + trigger append-only audit + indeks unik parsial
resources/views      Layout responsif, dashboard, usulan (tab), laporan, admin, templat cetak PDF
tests/               FullCycle · UatScenarios · SecurityAndRules · PagesSmoke
```
