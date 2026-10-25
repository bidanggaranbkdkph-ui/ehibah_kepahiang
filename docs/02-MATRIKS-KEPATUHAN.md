# 2 · Matriks Kepatuhan → Kontrol Sistem

Memetakan Matriks Kepatuhan (Perbup Kepahiang Hibah & Bansos) ke fitur, titik kontrol di kode, dan tes otomatis.
Status validasi hukum tiap baris tetap **"perlu paraf Bagian Hukum"** — dokumen ini bukan pendapat hukum.

| Pasal | Ketentuan (ringkas) | Fitur / kontrol di aplikasi | Titik kode | Tes |
|---|---|---|---|---|
| 1 | Istilah, pemisahan hibah vs bansos | Jenis hibah `UANG/BARANG`; `scope` HIBAH/BANSOS | `ReferenceSeeder`, `guardSubmit` | UAT-015 |
| 2 | Kriteria: spesifik, tidak wajib, tidak rutin, bermanfaat, memenuhi syarat, ber-NPHD; OPD pemilik urusan | Checklist 6 kriteria wajib lulus (`CRITERIA`); flag hibah berulang + alasan; `opd_id` | `applyAdminPass`, `guardRecommend`, `guardSubmit` | UAT-001 |
| 3–4 | Kategori penerima + ketentuan khusus; larangan tumpang tindih APBN | `recipient_categories`; pernyataan pendanaan ganda wajib; dokumen kondisional per kategori | `DocumentChecklist`, `guardSubmit` | UAT-002 |
| 5 | Legalitas badan/lembaga/ormas, domisili, pengesahan | Dokumen legalitas wajib + **tanggal berlaku** (kedaluwarsa dianggap belum lengkap) | `document_requirements`, `DocumentService::store` | UAT-002 |
| 6 | Isi usulan, RAB, panitia; digital; SKPD→TAPD→Bupati; daftar nominatif | Form usulan, `application_budget_items` (total dihitung server), alur lengkap, ekspor nominatif | `ApplicationController`, `WorkflowService`, `ReportService::nominatif` | UAT-003 |
| 7 | Keputusan Bupati: nama, alamat, besaran, ref Perda APBD & Perkada | `decrees` (nomor, tanggal, ref Perda & Perkada wajib); status *Ditetapkan* menolak tanpa Keputusan/alokasi | `applyApprove` | UAT-004, Blueprint-06 |
| 8 | NPHD ditandatangani Kepala SKPD berwenang | NPHD berversi; penandatangan harus Kepala OPD pemilik urusan | `ProcessService::draftNphd`, `guardSignNphd` | UAT-005 |
| 9 | Hibah uang LS, **70% lalu 30%**, pengecualian 100%, dokumen pencairan, SPP/SPM/SP2D, RKUD | `GrantCalculator::stagePlan`, `DisbursementService` (tahap II terkunci sebelum laporan tahap I diterima); pengecualian = alasan + otorisasi BPKAD | `DisbursementService` | UAT-006/007/008, Blueprint-08/09 |
| 10–12 | Bantuan keuangan **parpol**: DPA, Keputusan, permohonan ketua+sekretaris, berita acara verifikasi, SPP/SPM/SP2D | Kategori `PARPOL`: dokumen khusus (AD/ART, rekomendasi Kesbangpol, permohonan, BA verifikasi), ketua & sekretaris wajib | `ReferenceSeeder`, `guardSubmit` | UAT-014 |
| 13–14 | Hibah barang: pengadaan sesuai aturan; proposal, NPHD, identitas PJ, pakta integritas, **BAST** | `GoodsService`: ref pengadaan, daftar barang, BAST + pakta C.2 wajib; tidak ada jalur SP2D | `GoodsService` | UAT-009, Blueprint-10 |
| 15, 17 | Dokumen pertanggungjawaban SKPD (uang/barang), penatausahaan barang | Guard penutupan: bukti transfer RKUD lengkap / BAST tercatat, aset dicatat BPKAD | `guardClose` | full-cycle |
| 16 | LPJ penerima: laporan, pernyataan, bukti sah; asli di penerima, salinan via SKPD; format Lampiran E | `accountability_reports` + rincian realisasi (total dihitung server) + dokumen LPJ wajib | `AccountabilityService`, `guardSubmitLpj` | UAT-010 |
| 18 | LPJ parpol setelah pemeriksaan BPK | Tanggal pemeriksaan BPK wajib | `guardSubmitLpj` | — |
| 19 | LPJ ≤ 1 bulan setelah selesai / 10 Januari; SKPD memberi peringatan | Hitung tenggat, pengingat H-7/3/1, tandai tunggakan, surat peringatan berjenjang | `GrantCalculator::lpjDueDate`, `LpjMonitor` | UAT-011, Blueprint-11 |
| 20 | Sisa hibah uang disetor ke kas daerah; bukti ke SKPD & tembusan BUD | Sisa = tersalur − realisasi; setoran + ref kas + bukti; penutupan diblokir bila belum | `AccountabilityService::recordReturn`, `guardClose` | UAT-012, Blueprint-13 |
| 21 | Monev oleh SKPD; hasil ke Bupati, tembusan Inspektorat | `monitoring_records` + bukti distribusi + tindak lanjut; penutupan butuh minimal 1 monev & tak ada TL terbuka | `recordMonitoring` | UAT-013 |
| 22–44 | **Bansos** | Di luar workflow: jenis bansos nonaktif & ditolak validasi | `guardSubmit`, `ApplicationController::validated` | UAT-015 |
| 45–46 | Pencabutan Perbup 18/2014 & 62/2019; berlaku 18 Des 2023 | `regulations.status`; regulasi dicabut tidak dapat jadi dasar usulan | `guardSubmit` | UAT-016 |

## Lampiran

| Lampiran | Wujud di aplikasi |
|---|---|
| A — Rekomendasi | PDF `print/rekomendasi` (nilai & nama identik dengan data) |
| B.1 / B.2 — NPHD uang / barang | PDF `print/nphd-uang`, `print/nphd-barang` (dipilih otomatis) |
| C.1 / C.2 — Pakta integritas | PDF `print/pakta` (varian uang/barang); dokumen wajib terpisah per bentuk hibah |
| D — Pernyataan tanggung jawab | PDF `print/tanggung-jawab`; dokumen wajib pencairan uang |
| E.1–E.4 — Laporan penggunaan | Form laporan + dokumen wajib `LAPORAN_E`, rekening koran, bukti, dokumentasi |
| F–G — Bansos | Sengaja tidak ada |

> Templat PDF adalah **templat kerja**. Isi harus disetarakan dengan lampiran resmi oleh Bagian Hukum sebelum digunakan resmi.
