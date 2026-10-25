<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi E-Hibah Kabupaten Kepahiang
|--------------------------------------------------------------------------
| Semua aturan bisnis yang berasal dari Blueprint, Matriks Kepatuhan dan
| Perbup Kepahiang tentang Hibah dipusatkan di sini. Nilai numerik dapat
| ditimpa lewat tabel `settings` (menu Admin > Konfigurasi) tanpa ubah kode.
*/

return [

    'name' => 'E-Hibah Kabupaten Kepahiang',

    // ------------------------------------------------------------------
    // Peran (Blueprint Bab 3 & 6) + Admin Sistem (dikelola terpisah)
    // ------------------------------------------------------------------
    'roles' => [
        'PEMOHON'     => 'Pemohon (Penerima Hibah)',
        'OPD'         => 'OPD Teknis',
        'VERIFIKATOR' => 'Tim Verifikasi',
        'TAPD'        => 'TAPD',
        'BUPATI'      => 'Bupati / Pejabat Berwenang',
        'BPKAD'       => 'BPKAD / BUD',
        'INSPEKTORAT' => 'Inspektorat',
        'ADMIN'       => 'Admin Sistem',
    ],

    // Peran yang wajib MFA (TOTP) bila setting mfa.enforced = true
    'mfa_roles' => ['ADMIN', 'BPKAD', 'BUPATI', 'TAPD', 'INSPEKTORAT'],

    // ------------------------------------------------------------------
    // Matriks hak akses (Blueprint Bab 6)
    //   F = kelola penuh (buat, ubah, setujui)   P = proses / verifikasi
    //   V = lihat saja                           (tidak ada) = tanpa akses
    // ------------------------------------------------------------------
    'features' => [
        'profil'            => ['Akun & profil sendiri', 'Umum'],
        'pengajuan'         => ['Pengajuan usulan', 'Pengajuan & penilaian'],
        'verifikasi_adm'    => ['Verifikasi administrasi', 'Pengajuan & penilaian'],
        'verifikasi_teknis' => ['Verifikasi teknis', 'Pengajuan & penilaian'],
        'rekomendasi'       => ['Rekomendasi', 'Pengajuan & penilaian'],
        'penetapan'         => ['Penetapan penerima', 'Penetapan & anggaran'],
        'anggaran'          => ['Anggaran, pagu & alokasi', 'Penetapan & anggaran'],
        'nphd'              => ['NPHD', 'Penetapan & anggaran'],
        'penyaluran'        => ['Penyaluran (uang / barang)', 'Penyaluran & pertanggungjawaban'],
        'lpj'               => ['Laporan / LPJ', 'Penyaluran & pertanggungjawaban'],
        'monev'             => ['Monitoring & evaluasi', 'Penyaluran & pertanggungjawaban'],
        'koreksi_data'      => ['Koreksi data', 'Penyaluran & pertanggungjawaban'],
        'dashboard_laporan' => ['Dasbor & laporan', 'Pengawasan'],
        'audit_trail'       => ['Audit trail proses', 'Pengawasan'],
        'kelola_pengguna'   => ['Kelola pengguna & validasi akun', 'Administrasi sistem'],
        'data_master'       => ['Data master (OPD, tahun, jenis, dokumen, regulasi)', 'Administrasi sistem'],
        'konfigurasi'       => ['Konfigurasi sistem', 'Administrasi sistem'],
        'backup'            => ['Backup & pemulihan', 'Administrasi sistem'],
        'log_sistem'        => ['Log sistem & keamanan', 'Administrasi sistem'],
    ],

    'permissions' => [
        'profil'            => ['PEMOHON' => 'F', 'OPD' => 'F', 'VERIFIKATOR' => 'F', 'TAPD' => 'F', 'BUPATI' => 'F', 'BPKAD' => 'F', 'INSPEKTORAT' => 'F', 'ADMIN' => 'F'],
        'pengajuan'         => ['ADMIN' => 'F', 'PEMOHON' => 'F', 'OPD' => 'V', 'VERIFIKATOR' => 'V', 'INSPEKTORAT' => 'V'],
        'verifikasi_adm'    => ['ADMIN' => 'F', 'OPD' => 'P', 'VERIFIKATOR' => 'V', 'INSPEKTORAT' => 'V'],
        'verifikasi_teknis' => ['ADMIN' => 'F', 'OPD' => 'V', 'VERIFIKATOR' => 'P', 'INSPEKTORAT' => 'V'],
        'rekomendasi'       => ['ADMIN' => 'F', 'OPD' => 'F', 'VERIFIKATOR' => 'V', 'TAPD' => 'V', 'INSPEKTORAT' => 'V'],
        'penetapan'         => ['ADMIN' => 'F', 'OPD' => 'V', 'TAPD' => 'P', 'BUPATI' => 'F', 'BPKAD' => 'V', 'INSPEKTORAT' => 'V'],
        'anggaran'          => ['ADMIN' => 'F', 'OPD' => 'P', 'TAPD' => 'F', 'BPKAD' => 'F', 'INSPEKTORAT' => 'V'],
        'nphd'              => ['ADMIN' => 'F', 'PEMOHON' => 'P', 'OPD' => 'F', 'BUPATI' => 'V', 'BPKAD' => 'V', 'INSPEKTORAT' => 'V'],
        'penyaluran'        => ['ADMIN' => 'F', 'PEMOHON' => 'V', 'OPD' => 'P', 'BPKAD' => 'F', 'INSPEKTORAT' => 'V'],
        'lpj'               => ['ADMIN' => 'F', 'PEMOHON' => 'F', 'OPD' => 'P', 'BUPATI' => 'V', 'BPKAD' => 'V', 'INSPEKTORAT' => 'V'],
        'monev'             => ['ADMIN' => 'F', 'OPD' => 'F', 'TAPD' => 'V', 'BUPATI' => 'V', 'BPKAD' => 'V', 'INSPEKTORAT' => 'P'],
        'koreksi_data'      => ['ADMIN' => 'F', 'PEMOHON' => 'P', 'OPD' => 'P', 'VERIFIKATOR' => 'P', 'BPKAD' => 'P', 'INSPEKTORAT' => 'V'],
        'dashboard_laporan' => ['ADMIN' => 'F', 'OPD' => 'V', 'VERIFIKATOR' => 'V', 'TAPD' => 'V', 'BUPATI' => 'V', 'BPKAD' => 'V', 'INSPEKTORAT' => 'V'],
        'audit_trail'       => ['ADMIN' => 'F', 'BPKAD' => 'V', 'INSPEKTORAT' => 'F'],
        'kelola_pengguna'   => ['ADMIN' => 'F'],
        'data_master'       => ['ADMIN' => 'F', 'BPKAD' => 'V', 'TAPD' => 'V'],
        'konfigurasi'       => ['ADMIN' => 'F'],
        'backup'            => ['ADMIN' => 'F'],
        'log_sistem'        => ['ADMIN' => 'F', 'INSPEKTORAT' => 'V'],
    ],

    // ------------------------------------------------------------------
    // Nilai bawaan aturan bisnis (dapat ditimpa tabel `settings`)
    // ------------------------------------------------------------------
    'defaults' => [
        // Perbup Pasal 9: hibah uang bertahap 70% lalu 30%
        'disbursement.stage1_percent' => 70,
        // Pasal 19: LPJ 1 bulan setelah kegiatan selesai, paling lambat 10 Januari tahun berikutnya
        'lpj.months_after_activity'   => 1,
        'lpj.cap_enabled'             => true,
        'lpj.cap_day'                 => 10,
        'lpj.cap_month'               => 1,
        'lpj.reminder_days'           => [7, 3, 1],
        'lpj.warning_interval_days'   => 7,
        // Nilai minimal verifikasi teknis agar dinyatakan layak (0-100)
        'tech.min_score'              => 60,
        // Upload
        'upload.max_mb'               => 5,
        'upload.mimes'                => ['pdf', 'jpg', 'jpeg', 'png'],
        // Penutupan: wajib ada minimal satu catatan monitoring (Pasal 21)
        'close.require_monitoring'    => true,
        // Keamanan
        'security.max_login_attempts' => 5,
        'security.lockout_minutes'    => 15,
        'security.password_min'       => 10,
        'security.mfa_enforced'       => false,
        'backup.retention_days'       => 30,
        'audit.retention_years'       => 10,
    ],

    // Antivirus: 'none' | 'clamscan' (butuh ClamAV terpasang di server)
    'antivirus' => [
        'driver' => env('EHIBAH_ANTIVIRUS', 'none'),
        'binary' => env('EHIBAH_CLAMSCAN', 'clamscan'),
    ],

    // Direktori backup (di luar webroot)
    'backup_path' => env('EHIBAH_BACKUP_PATH', storage_path('app/backups')),

    // ------------------------------------------------------------------
    // Koreksi data: bidang yang boleh dikoreksi + peran penyetuju.
    // Nilai uang TIDAK boleh dikoreksi lewat jalur ini (wajib batal + ajukan ulang).
    // ------------------------------------------------------------------
    'corrections' => [
        'application'  => [
            'title'          => ['VERIFIKATOR', 'OPD'],
            'purpose'        => ['VERIFIKATOR', 'OPD'],
            'target'         => ['VERIFIKATOR', 'OPD'],
            'location'       => ['VERIFIKATOR', 'OPD'],
            'activity_start' => ['VERIFIKATOR', 'OPD'],
            'activity_end'   => ['VERIFIKATOR', 'OPD'],
        ],
        'nphd'         => ['ADMIN' => 'F', 
            'nphd_no'   => ['BPKAD', 'OPD'],
            'nphd_date' => ['BPKAD', 'OPD'],
        ],
        'disbursement' => [
            'spp_no'          => ['BPKAD'],
            'spm_no'          => ['BPKAD'],
            'sp2d_no'         => ['BPKAD'],
            'sp2d_date'       => ['BPKAD'],
            'rkud_ref'        => ['BPKAD'],
            'bank_account_ref' => ['BPKAD'],
        ],
        'decree'       => [
            'decree_no'   => ['BPKAD', 'VERIFIKATOR'],
            'decree_date' => ['BPKAD', 'VERIFIKATOR'],
        ],
    ],
];
