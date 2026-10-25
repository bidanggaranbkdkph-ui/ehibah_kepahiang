# 7 · Perhitungan, Pagu & Dokumen Wajib

## 7.1 Rumus (`app/Services/GrantCalculator.php`)

| Perhitungan | Rumus | Dasar | Tes |
|---|---|---|---|
| Penyaluran bertahap | Tahap I = ⌊nilai × 70 / 100⌋ ; Tahap II = nilai − Tahap I | Pasal 9 | UAT-006 (Rp100.000.000 → 70.000.000 / 30.000.000; selisih pembulatan ke tahap II, total selalu tepat) |
| Penyaluran 100% | Tahap I = 100% (hanya skema `FULL`, setelah otorisasi BPKAD) | Pasal 9 ayat 3 | UAT-007 |
| Persentase tahap I | `disbursement.stage1_percent` (bawaan 70) | Konfigurasi | — |
| Total RAB | Σ ⌈volume × harga satuan⌋ per baris; **dihitung server**, bukan dari input | Pasal 6 | `ApplicationController::validated` |
| Tenggat LPJ | `min( tgl selesai + 1 bulan , 10 Jan (TA + 1) )` | Pasal 19 | UAT-011 |
| Terlambat (hari) | `max(0, hari ini − tenggat)` | Pasal 19 | Blueprint-11 |
| Sisa dana | `max(0, dana tersalur − realisasi LPJ akhir)` | Pasal 20 | UAT-012 (100 jt − 95 jt = 5 jt) |
| Sisa pagu OPD | `pagu − Σ alokasi (RESERVED + CONFIRMED)`; alokasi baru ditolak bila melebihi | — | Blueprint-06b |
| Skor teknis | rata-rata 4 kriteria (0–100); **layak bila ≥ `tech.min_score` (60)** | Pasal 6 | — |
| Persentase realisasi | `tersalur / ditetapkan × 100` (dashboard) | Blueprint 9.1 | — |

Seluruh nilai uang disimpan sebagai **bilangan bulat rupiah (`bigint`)** — tidak ada pembulatan floating-point.

> **Penafsiran Pasal 19** — kalimat "1 bulan setelah kegiatan selesai *atau* paling lambat 10 Januari tahun berikutnya" dibaca sebagai
> "mana yang lebih awal". Bila Bagian Hukum menafsirkan lain, ubah di *Admin → Konfigurasi* (`lpj.cap_enabled`, `lpj.months_after_activity`).

## 7.2 Pagu & alokasi

1. TAPD atau BPKAD menetapkan **pagu per OPD per tahun anggaran** (menu *Pagu & Alokasi*), lengkap kode rekening & ref DPA.
2. TAPD mencatat pertimbangan → sistem **mencadangkan** dana (`RESERVED`) dengan penguncian baris pagu.
3. Bupati menetapkan → nilai ≤ alokasi; alokasi disesuaikan ke nilai ditetapkan.
4. BPKAD mengonfirmasi DPA → `CONFIRMED`. Penolakan/pembatalan → `RELEASED` (dana kembali ke pagu).
5. Pagu tidak boleh diturunkan di bawah dana yang sudah dialokasikan.

## 7.3 Dokumen wajib (`document_requirements`, dikelola di *Admin → Data Master → Dokumen wajib*)

Dokumen **kondisional** menurut tahap, bentuk hibah (uang/barang) dan kategori penerima. Dokumen berjangka (SK pengurus, domisili) wajib
mengisi tanggal berlaku; yang kedaluwarsa dianggap **belum lengkap**. Setiap unggahan = versi baru; versi lama tidak pernah dihapus.

| Tahap | Dokumen | Berlaku untuk |
|---|---|---|
| **Pengajuan** | Surat permohonan; Proposal; RAB/daftar kebutuhan; Susunan panitia; Pengesahan penanggung jawab; KTP penanggung jawab | semua |
| | Rekening bank atas nama lembaga | hibah uang |
| | NPWP | selain pemerintah |
| | Akta pendirian & pengesahan badan hukum | badan/lembaga, ormas |
| | SK kepengurusan *(berjangka)*; Keterangan domisili Kepahiang *(berjangka)* | badan/lembaga, ormas (SK juga parpol) |
| | Dokumen pendirian BUMN/BUMD | BUMN/BUMD |
| | Pernyataan non-tumpang tindih APBN; Keterangan lokasi satker | pemerintah pusat/daerah lain |
| | AD/ART; Rekomendasi Kesbangpol | partai politik |
| **NPHD** | NPHD bertanda tangan kedua pihak (Lampiran B.1/B.2) | semua |
| **Pencairan (uang)** | Pakta integritas C.1; Surat permohonan pencairan; Kuitansi; Pernyataan tanggung jawab (D) | hibah uang |
| | Permohonan ditandatangani ketua+sekretaris (kop/stempel); Berita acara verifikasi (Pasal 12) | parpol |
| **Penyerahan (barang)** | Pakta integritas C.2; Kesediaan menerima; Identitas penanggung jawab; **BAST** | hibah barang |
| **LPJ** | Laporan penggunaan (Lampiran E); Dokumentasi | semua |
| | Bukti pengeluaran sah; Rekening koran | hibah uang |
| | Bukti setor sisa dana | bila ada sisa (diperiksa saat penutupan) |
| | Hasil pemeriksaan BPK | parpol (tanggal diwajibkan saat LPJ) |

## 7.4 Keamanan berkas

| Kontrol | Implementasi |
|---|---|
| Tipe & ukuran | PDF/JPG/PNG, maks. 5 MB (`upload.max_mb`), diverifikasi **ekstensi + magic bytes** (exe berkedok .pdf ditolak) |
| Anti malware | ClamAV opsional (`EHIBAH_ANTIVIRUS=clamscan`), **gagal-tertutup** bila pemindai error |
| Penyimpanan | disk privat `storage/app/private/applications/{id}/…`; nama acak; **tidak ada URL publik** (`serve=false`) |
| Akses | hanya lewat `DocumentController::download` (cek cakupan data) + audit `DOCUMENT_DOWNLOADED` |
| Integritas | SHA-256 per berkas tersimpan; riwayat versi |
