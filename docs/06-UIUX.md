# 6 · Rancangan UI/UX

## 6.1 Prinsip

1. **Satu layar, satu keputusan** — halaman usulan menampilkan posisi (stepper), siapa yang memegang bola, dan hanya tindakan yang boleh dilakukan peran itu.
2. **Alasan, bukan sekadar larangan** — tombol yang belum boleh dijalankan menampilkan daftar prasyarat yang kurang ("Dokumen belum dinyatakan sesuai: …").
3. **Berjenjang & terarah** — formulir panjang dipecah per kartu bernomor; dokumen wajib berupa checklist dengan progres.
4. **Ponsel lebih dulu** — layout CSS mobile-first: sidebar menjadi laci, navigasi bawah (Beranda · Usulan · Notifikasi · Menu), tabel menjadi kartu.
5. **Aksesibel** — kontras ≥ 4.5:1, label pada semua input, fokus terlihat, ikon selalu disertai teks, status tidak hanya dengan warna.
6. **Tanpa ketergantungan luar** — tanpa CDN/font eksternal, cocok untuk jaringan pemerintah dan CSP ketat.

## 6.2 Sistem desain (`public/css/app.css`)

| Token | Nilai | Pemakaian |
|---|---|---|
| `--navy` `#0B2A5B` | warna utama | sidebar, judul, tabel header |
| `--em-2` `#0E9F6E` | aksen aksi | tombol utama, "selesai", progres |
| `--gold` `#C9A227` | perhatian | KPI realisasi, status penetapan |
| `--red` `#B42318` / `--amber` `#B45309` | bahaya / peringatan | tunggakan, penolakan, dikembalikan |
| Status badge | `gray` draf · `blue` pengajuan/verifikasi · `violet` TAPD · `gold` penetapan/anggaran · `teal` penyaluran · `green` selesai · `amber` dikembalikan · `red` ditolak/batal | `Status::tone()` |
| Komponen | `card`, `kpi`, `badge`, `btn (primary/navy/danger/warning)`, `alert`, `table.responsive`, `stepper`, `timeline`, `doc-item`, `repeat-row`, `tabs`, `bar` | |

## 6.3 Peta halaman

```
Publik          /                 portal + statistik publik non-sensitif + alur
                /cek-status       status & tahap berdasar no. register (tanpa nama/nilai)
                /masuk /daftar    login (anti brute-force) · registrasi pemohon
Semua peran     /dashboard        KPI + antrean tindakan + sebaran status + grafik
                /usulan           daftar (filter: kata kunci, status, TA, OPD)
                /usulan/{id}      detail bertab: Ringkasan · Dokumen · Penilaian · Penetapan · NPHD ·
                                  Penyaluran/Penyerahan · LPJ & Monev · Koreksi · Riwayat  (+ panel Tindakan)
                /notifikasi /profil /hak-akses
Pemohon         /usulan/baru, /usulan/{id}/ubah     formulir 5 kartu (identitas, uraian, RAB, panitia, pernyataan)
Petugas         /verifikasi       halaman verifikasi = antrean tindakan per status
                /monev /koreksi /pagu /keputusan /laporan[/{slug}] /audit
Admin Sistem    /admin/pengguna (validasi akun) · /admin/master/{entitas} · /admin/konfigurasi · /admin/backup
```

## 6.4 Wireframe kunci

### Dashboard (desktop)
```
┌ Sidebar ┬─────────────────────────────────────────────────────────────────┐
│ E-Hibah │ Dashboard                                🔔3   [BS Budi ▾]  ⎋     │
│ ─────── │ Selamat datang, Budi            [TA 2026 ▾]  [+ Usulan baru]      │
│ Beranda │ ┌Jumlah usulan┐┌Nilai usulan┐┌Realisasi penyaluran┐┌Tunggakan LPJ┐│
│ Usulan  │ │     9       ││ Rp 965 jt  ││ Rp 28 jt (6,1%)    ││  1 (merah)  ││
│ …       │ └─────────────┘└────────────┘└────────────────────┘└─────────────┘│
│         │ ┌ Menunggu tindakan Anda ───────────────┐ ┌ Jalan pintas ────────┐│
│         │ │ ▸ Pengadaan Alat Peraga … [Draf]      │ │ Daftar usulan        ││
│         │ │ ▸ Pelatihan Guru …  [Diajukan]        │ │ Cek status publik    ││
│         │ └───────────────────────────────────────┘ │ Matriks hak akses    ││
│         │ ┌ Sebaran status ──┐ ┌ Usulan per OPD ───┐ └──────────────────────┘│
└─────────┴─────────────────────────────────────────────────────────────────┘
```

### Formulir usulan
```
1 · Identitas usulan   [Judul………] [Bentuk ▾] [OPD ▾] [Dasar hukum ▾] [Lokasi] [Mulai] [Selesai]
2 · Uraian usulan      [Latar belakang] [Maksud & tujuan] [Sasaran] [Program/kegiatan]
3 · RAB                [Uraian | Vol | Satuan | Harga | Jumlah | ✕] … [+ baris]   ▐ Total Rp … ▌(otomatis)
4 · Panitia            [Nama | Jabatan | ✕] … [+ anggota]
5 · Pernyataan         ☐ tidak pendanaan ganda  ☐ hibah berulang → [alasan]
                       [Simpan sebagai draf]   → unggah dokumen di halaman usulan
```

### Halaman verifikasi / detail usulan (peran OPD, status Verifikasi Administrasi)
```
┌ Judul usulan ─────────────────────────────────────────────  [Verifikasi Administrasi] ┐
│ ① Draf ✓ ─ ② Diajukan ✓ ─ ③ Verifikasi Adm ● ─ ④ Teknis ○ ─ … ─ ⑫ Selesai ○            │
├ Ringkasan │ Dokumen (4 kurang) │ Penilaian │ Penetapan │ Koreksi │ Riwayat ────────────┤
│ [Dokumen]                                         │ ┌ Tindakan ────────────────────────┐│
│  ✔ Proposal *        v2  Sesuai        [Unduh]    │ │ ⚠ Prasyarat belum terpenuhi:      ││
│  ! RAB *             v1  Menunggu [Periksa ▾]     │ │   • dokumen belum sesuai: …       ││
│  ＋ Domisili *       Belum diunggah               │ │ Checklist Pasal 2  ☑☑☑☐☐☑         ││
│                                                   │ │ [Lulus] [Kembalikan] [Tolak]      ││
└───────────────────────────────────────────────────┴──────────────────────────────────────┘
```

### Matriks hak akses
Tabel peran × modul dengan simbol F/P/V/– dan kolom peran pengguna disorot; dilengkapi catatan cakupan data per peran.

## 6.5 Prototipe mobile

`public/prototype/mobile.html` (klik-able, tanpa dependensi): **Beranda**, **Status usulan** (timeline), **Unggah dokumen** (kamera/berkas, tombol *Ajukan* aktif hanya bila dokumen wajib lengkap),
**Notifikasi**, **LPJ tahap I** (realisasi otomatis), dan **Verifikasi OPD** (checklist Pasal 2). Pada layar lebar semua layar tampil berdampingan; pada ponsel tampil satu per satu.
Aplikasi sesungguhnya sudah responsif — halaman yang sama dapat dipakai di ponsel (UAT-16).

## 6.6 Notifikasi

| Pemicu | Penerima | Kanal |
|---|---|---|
| Setiap perubahan status | Pemohon (selalu) + peran yang berikutnya harus bertindak | in-app (lonceng) + surel |
| Usulan ditolak/dibatalkan | Pemohon + OPD | in-app + surel |
| Laporan tahap I diajukan / hasil pemeriksaan | OPD / Pemohon | in-app + surel |
| Pengingat LPJ H-7/H-3/H-1/H-0 | Pemohon + OPD | in-app + surel |
| LPJ lewat tenggat + surat peringatan berjenjang | Pemohon, OPD, Inspektorat | in-app + surel |
| Setoran sisa dana | OPD + BPKAD (tembusan BUD) | in-app + surel |
| Monitoring baru | Bupati + Inspektorat (tembusan) | in-app + surel |
| Koreksi data menunggu | penyetuju berwenang | in-app + surel |
