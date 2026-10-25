# Keputusan Terbuka & Asumsi (Blueprint Bab 13)

Blueprint mensyaratkan enam keputusan *sebelum coding*. Aplikasi dibangun dengan asumsi bawaan di bawah ini; semuanya **dapat diubah lewat
konfigurasi/data master tanpa mengubah kode**, kecuali yang ditandai ⚙ (butuh perubahan kode kecil).

## 1 · Dasar hukum (Bagian Hukum)
| Hal | Asumsi saat ini | Tindakan Pemda |
|---|---|---|
| Nomor/tahun Perbup Hibah (2023 vs 2025) | Perbup **25/2023** | Konfirmasi; ubah di *Data Master → Regulasi* |
| Keputusan Sekda 900-43/2023 (Tim Verifikasi & Sekretariat) | satu entri `PERLU_DICEK` | Pastikan nomor |
| Perda 6/2021, Permendagri 99/2019 | `PERLU_DICEK`, tidak dapat jadi dasar usulan | Verifikasi lalu set `AKTIF` |
| Templat lampiran A–E | templat kerja PDF | Samakan dengan lampiran resmi (`resources/views/print/*`) ⚙ |
| Tafsir Pasal 19 (1 bulan **atau** 10 Januari) | yang lebih awal | Putuskan; atur `lpj.cap_enabled` |
| Kategori parpol & Kesbangpol | satu workflow dengan dokumen/aturan khusus; Kesbangpol bertindak sebagai OPD | Tentukan apakah perlu SOP terpisah |

## 2 · Pemilik proses & kewenangan (BPKAD/OPD)
| Hal | Asumsi | Tindakan |
|---|---|---|
| OPD memverifikasi administrasi usulan di urusannya; Tim Verifikasi lintas OPD menilai teknis | sesuai matriks | Tetapkan anggota tim & sekretariat |
| Penandatangan NPHD | pengguna berperan OPD dengan jabatan diawali **"Kepala"** pada OPD pemilik urusan | Sesuaikan aturan jabatan (mis. Plt./Kuasa) ⚙ |
| Pengecualian pencairan 100% | diajukan OPD, diotorisasi BPKAD | Konfirmasi pejabat pemberi otorisasi |
| Pencatatan aset hibah barang | BPKAD (pengelola barang) | Konfirmasi |
| Penyetuju koreksi data | per bidang di `config/ehibah.php → corrections` | Tetapkan peran penyetuju |

## 3 · Jenis hibah & persyaratan
| Hal | Asumsi | Tindakan |
|---|---|---|
| Jenis | Hibah Uang, Hibah Barang/Jasa | Tambah jenis bila ada |
| Hibah **jasa** | diperlakukan seperti barang (BAST) | Konfirmasi |
| Dokumen wajib per kategori | lihat docs/07 | Tinjau; ubah di *Data Master → Dokumen wajib* |
| Skor teknis | 4 kriteria, layak ≥ 60 | Tetapkan kriteria/bobot resmi ⚙ (kriteria) / konfigurasi (ambang) |
| Pagu | satu pagu per OPD per tahun | Bila pagu per jenis/rekening, perluas `budget_ceilings` ⚙ |

## 4 · Data & integrasi
| Sistem | Status | Kebutuhan dari Pemda |
|---|---|---|
| SIPD RI / sistem keuangan | **belum terhubung**; pagu & DPA diinput manual (`dpa_ref`, `account_code`) | spesifikasi API resmi + izin |
| TTE BSrE | **belum**; tanda tangan basah/ref TTE dicatat | akun/kontrak BSrE |
| SSO Pemda | belum | IdP + protokol (OIDC/SAML) |
| Surel | SMTP standar | server SMTP Pemda |
| Data OPD | belum di-seed (hanya demo) | daftar OPD resmi + kode |

## 5 · Standar layanan
| Hal | Asumsi | Tindakan |
|---|---|---|
| Batas waktu layanan verifikasi (SLA) | belum diberlakukan (hanya "menunggu sejak" di antrean) | Tetapkan tenggat per tahap; dapat ditambah sebagai pengingat ⚙ |
| Masa pengajuan | per tahun anggaran (`submission_open/close`) | Isi tanggal buka/tutup |
| Format nomor register | `EH/{tahun}/{kode OPD}/{urut}` | Konfirmasi |
| Ukuran/tipe berkas | PDF/JPG/PNG ≤ 5 MB | Sesuaikan di Konfigurasi |

## 6 · Operasional
| Hal | Asumsi | Tindakan |
|---|---|---|
| Sesi | 30 menit tidak aktif | Sesuaikan `SESSION_LIFETIME` |
| MFA | tersedia; wajib untuk peran istimewa **bila diaktifkan** (bawaan nonaktif agar pelatihan mudah) | **Aktifkan sebelum go-live** |
| Retensi | backup 30 hari; audit & data permohonan tidak dihapus | Tetapkan kebijakan retensi arsip |
| RPO/RTO | ≤ 24 jam / ≤ 4 jam (backup harian) | Setujui atau aktifkan WAL archiving |
| Hosting | belum ditentukan | Pilih (server Pemda / cloud pemerintah) dan penanggung jawab TI |

## Batasan yang diketahui

1. **Templat PDF** (NPHD, rekomendasi, pakta, surat peringatan) adalah templat kerja, bukan salinan lampiran resmi.
2. Surat peringatan LPJ diterbitkan otomatis oleh sistem sebagai catatan + PDF; pengiriman resmi (tanda tangan Kepala SKPD) dilakukan di luar sistem.
3. Tidak ada modul bansos (sengaja).
4. Tidak ada integrasi SIPD/BSrE/SSO (menunggu API & izin resmi).
5. Pencadangan memerlukan salinan di luar server — aplikasi menghasilkan arsip, tidak mengirimnya ke lokasi lain.
6. Belum ada uji beban/penetrasi independen (disyaratkan Blueprint Bab 10 sebelum go-live).
