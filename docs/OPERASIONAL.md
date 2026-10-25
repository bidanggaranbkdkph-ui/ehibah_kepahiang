# Panduan Operasional

## 1. Arsitektur penerapan yang direkomendasikan

```
Internet/Intranet ── HTTPS (TLS 1.2+) ── Nginx ── PHP-FPM 8.3 ── Laravel
                                           │                       │
                                           │                       ├── PostgreSQL 16 (server terpisah/loopback)
                                           │                       └── storage/app/private (dokumen) — luar webroot
                                           └── cron: schedule:run (tiap menit)
Backup: /var/backups/ehibah ──(salin harian, terenkripsi)──> lokasi terpisah (NAS/objek storage/gedung lain)
```

Spesifikasi awal: 2 vCPU, 4 GB RAM, 50 GB SSD (dokumen tumbuh ±5 MB/usulan). Seluruh aplikasi tanpa Node/Redis/queue worker.

## 2. Instalasi produksi (Ubuntu 22.04/24.04)

```bash
# 1. Paket
sudo apt install nginx postgresql php8.3-fpm php8.3-{pgsql,mbstring,xml,zip,gd,intl,curl,bcmath} unzip git clamav clamav-daemon
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# 2. Database
sudo -u postgres psql -c "CREATE ROLE ehibah LOGIN PASSWORD '<kuat>';"
sudo -u postgres psql -c "CREATE DATABASE ehibah OWNER ehibah ENCODING 'UTF8' TEMPLATE template0;"

# 3. Aplikasi
sudo mkdir -p /var/www/ehibah && sudo chown $USER /var/www/ehibah
git clone <repo> /var/www/ehibah && cd /var/www/ehibah
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate     # isi DB_*, APP_URL, MAIL_*, EHIBAH_BACKUP_PATH
php artisan migrate --force
php artisan db:seed --class=ReferenceSeeder --force   # data referensi saja (BUKAN DemoSeeder)
php artisan ehibah:create-admin admin@kepahiangkab.go.id "Admin Sistem"   # kata sandi acak ditampilkan sekali
# Setelah login: isi Data Master (OPD, pagu, jenis hibah, regulasi) lalu buat akun petugas di Admin > Pengguna
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 4. Izin
sudo chown -R www-data:www-data storage bootstrap/cache
sudo mkdir -p /var/backups/ehibah && sudo chown www-data /var/backups/ehibah && sudo chmod 700 /var/backups/ehibah

# 5. Penjadwal
echo "* * * * * www-data cd /var/www/ehibah && php artisan schedule:run >> /dev/null 2>&1" | sudo tee /etc/cron.d/ehibah
```

Nginx: arahkan `root` ke `/var/www/ehibah/public`, tolak akses dotfile, `client_max_body_size 12m;`, aktifkan HTTPS + HSTS.
`php.ini`: `memory_limit=512M` (PDF laporan besar), `upload_max_filesize=10M`, `post_max_size=12M`, `expose_php=Off`.

**Jangan** menjalankan `DemoSeeder` di produksi (otomatis dilewati bila `APP_ENV=production`; tidak ada akun demo bawaan).

## 3. Checklist keamanan sebelum go-live

| ✔ | Kontrol | Cara |
|---|---|---|
| ☐ | `APP_ENV=production`, `APP_DEBUG=false` | `.env` |
| ☐ | HTTPS penuh; `SESSION_SECURE_COOKIE=true`; `SESSION_ENCRYPT=true` | `.env`, Nginx |
| ☐ | Kata sandi DB kuat; DB tidak terbuka ke publik (`listen_addresses`, firewall) | PostgreSQL |
| ☐ | **MFA wajib** untuk Admin/BPKAD/Bupati/TAPD/Inspektorat | Admin → Konfigurasi → *Wajibkan MFA* |
| ☐ | Kebijakan sandi (min. 10) & kunci akun (5× gagal → 15 menit) sesuai kebijakan Pemda | Konfigurasi |
| ☐ | ClamAV aktif: `EHIBAH_ANTIVIRUS=clamscan`, `freshclam` terjadwal | `.env` |
| ☐ | `TRUSTED_PROXIES` diisi bila di belakang proxy/LB | `.env` |
| ☐ | Backup terjadwal aktif & salinan di **lokasi terpisah**; uji pemulihan (bagian 5) | cron, runbook |
| ☐ | `php artisan audit:verify` hijau; baris audit tak dapat diubah (uji trigger) | tes UAT-14 |
| ☐ | Akun hanya perorangan (tidak berbagi); admin tak merangkap peran proses | Admin → Pengguna |
| ☐ | Pembaruan keamanan OS/PHP/PostgreSQL berkala; `composer audit` tiap rilis | prosedur TI |
| ☐ | Penetration test / uji kerentanan oleh pihak independen (Bab 10 Blueprint) | pihak ketiga |

Header HTTP yang dipasang aplikasi: `Content-Security-Policy (default-src 'self')`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
`Referrer-Policy`, `Permissions-Policy`, `HSTS` (saat HTTPS), `Cache-Control: no-store` untuk halaman terautentikasi.

## 4. Backup

| Jenis | Perintah | Isi | Jadwal bawaan |
|---|---|---|---|
| `DB` | `php artisan ehibah:backup DB --verify` | `pg_dump -Fc` + manifest SHA-256 | harian 01:00 |
| `FILES` | `php artisan ehibah:backup FILES --verify` | arsip dokumen `applications/` | — |
| `FULL` | `php artisan ehibah:backup FULL --verify` | database + dokumen | mingguan (Minggu 02:00) |

- Hasil: `ehibah_{JENIS}_{waktu}.zip` berisi dump/arsip + `manifest.json`; dicatat di tabel `backup_logs` dan menu **Admin → Backup**.
- `--verify` / tombol *Uji keutuhan*: memeriksa checksum paket, arsip dapat dibuka, dan checksum tiap bagian cocok dengan manifest.
- Retensi otomatis `backup.retention_days` (30 hari) di direktori backup — **salinan di luar server tetap wajib** (3-2-1: 3 salinan, 2 media, 1 di luar lokasi).
- Enkripsi: simpan arsip pada volume terenkripsi atau enkripsi sebelum dikirim keluar (mis. `age`/`gpg`).
- Target: **RPO ≤ 24 jam** (backup DB harian), **RTO ≤ 4 jam**. Untuk RPO lebih kecil aktifkan WAL archiving PostgreSQL.

## 5. Runbook pemulihan (diuji)

```bash
# 1. Ambil paket terbaru & verifikasi
cd /var/backups/ehibah && sha256sum ehibah_FULL_*.zip         # cocokkan dengan backup_logs.sha256
unzip ehibah_FULL_YYYYMMDD_HHMMSS.zip -d /tmp/restore

# 2. Database (ke basis data kosong/baru)
sudo -u postgres createdb -O ehibah ehibah_pulih
pg_restore -U ehibah -d ehibah_pulih --no-owner /tmp/restore/db_*.dump

# 3. Berkas dokumen
unzip /tmp/restore/files_*.zip -d /var/www/ehibah/storage/app/private/

# 4. Arahkan aplikasi (DB_DATABASE=ehibah_pulih) lalu verifikasi
php artisan audit:verify          # rantai hash harus "utuh"
php artisan migrate:status        # semua migrasi "Ran"
```

Hasil uji pemulihan pada pengembangan (PostgreSQL 16.9): backup `FULL` → `pg_restore` ke database kosong → jumlah baris
`grant_applications/application_documents/disbursements` identik, `audit:verify` pada hasil pulih = **utuh**.

Pemulihan titik waktu (PITR) memerlukan WAL archiving; di luar cakupan konfigurasi bawaan.

## 6. Pemantauan & rutinitas

| Rutinitas | Siapa | Frekuensi |
|---|---|---|
| Cek log `storage/logs/laravel-*.log` & kegagalan backup (dashboard Admin) | Admin Sistem | harian |
| `php artisan audit:verify` (terjadwal 03:30) — notifikasi bila rusak | Admin + Inspektorat | harian |
| Validasi registrasi pemohon baru (SOP 1) | Admin Sistem | harian |
| Tinjau akun terkunci / tidak aktif; reset MFA | Admin Sistem | mingguan |
| Uji pemulihan backup | Admin + TI | **bulanan** (UAT-15) |
| Tinjau matriks hak akses & penugasan peran | Sekretariat Tim Verifikasi + Admin | per semester |
| Tutup tahun anggaran: ubah status TA → TUTUP, buka TA baru + pagu | Admin + BPKAD | tahunan |

## 7. Pemecahan masalah

| Gejala | Penyebab umum | Tindakan |
|---|---|---|
| "Pemindaian keamanan berkas gagal" | ClamAV tidak berjalan (gagal-tertutup) | `systemctl start clamav-daemon` atau set `EHIBAH_ANTIVIRUS=none` sementara (catat risikonya) |
| PDF laporan kosong/memori habis | laporan sangat besar | persempit filter / pakai Excel; naikkan `memory_limit` |
| Surel tidak terkirim | `MAIL_*` belum diisi | notifikasi in-app tetap tersimpan; perbaiki SMTP |
| "Data usulan telah diubah pengguna lain" | penguncian optimistis | muat ulang halaman, ulangi aksi |
| Rantai audit rusak | manipulasi/pemulihan parsial | **jangan** perbaiki manual; eskalasi ke Inspektorat, pulihkan dari backup terakhir yang `audit:verify`-nya hijau |
