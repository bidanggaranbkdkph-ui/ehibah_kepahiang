<?php
use App\Http\Controllers\Admin\ScheduleController;

// Di dalam group middleware admin:
Route::get('/admin/schedules', [ScheduleController::class, 'index'])->name('admin.schedules.index');
Route::put('/admin/schedules/{schedule}', [ScheduleController::class, 'update'])->name('admin.schedules.update');
Route::post('/admin/schedules/{schedule}/toggle', [ScheduleController::class, 'quickToggle'])->name('admin.schedules.toggle');
use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DecreeController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MatrixController;
use App\Http\Controllers\MonevController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProcessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- publik
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/cek-status', [PublicController::class, 'status'])->name('public.status')->middleware('throttle:20,1');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [LoginController::class, 'show'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/daftar', [RegisterController::class, 'show'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [LoginController::class, 'destroy'])->name('logout');

    // MFA (tanpa 'mfa' middleware agar tidak berputar)
    Route::get('/mfa/verifikasi', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('/mfa/verifikasi', [MfaController::class, 'verify'])->middleware('throttle:10,1')->name('mfa.verify');
    Route::get('/mfa/aktivasi', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('/mfa/aktivasi', [MfaController::class, 'enable'])->name('mfa.enable');
});

// ------------------------------------------------------- aplikasi (login)
Route::middleware(['auth', 'active', 'mfa'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::get('/notifikasi/{id}/buka', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/lembaga', [ProfileController::class, 'updateOrganization'])->name('profile.organization');
    Route::put('/profil/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profil/mfa/nonaktif', [MfaController::class, 'disable'])->name('mfa.disable');

    Route::get('/hak-akses', [MatrixController::class, 'index'])->name('matrix');
    Route::put('/hak-akses', [MatrixController::class, 'update'])->name('matrix.update')->middleware('perm:kelola_pengguna,F');
    Route::post('/hak-akses/reset', [MatrixController::class, 'reset'])->name('matrix.reset')->middleware('perm:kelola_pengguna,F');

    // ---- usulan
    Route::get('/usulan', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/usulan/baru', [ApplicationController::class, 'create'])->name('applications.create')->middleware('perm:pengajuan,F');
    Route::post('/usulan', [ApplicationController::class, 'store'])->name('applications.store')->middleware('perm:pengajuan,F');
    Route::get('/usulan/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/usulan/{application}/ubah', [ApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('/usulan/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::post('/usulan/{application}/aksi/{action}', [ProcessController::class, 'transition'])->name('applications.action');

    // ---- dokumen
    Route::post('/usulan/{application}/dokumen', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/dokumen/{document}', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/dokumen/{document}/periksa', [DocumentController::class, 'review'])->name('documents.review');

    // ---- proses
    Route::get('/verifikasi', [ApplicationController::class, 'queue'])->name('queue');
    Route::post('/usulan/{application}/penilaian-teknis', [ProcessController::class, 'technical'])->name('process.technical');
    Route::post('/usulan/{application}/pertimbangan-tapd', [ProcessController::class, 'tapd'])->name('process.tapd');
    Route::post('/usulan/{application}/nphd', [ProcessController::class, 'nphd'])->name('process.nphd');
    Route::post('/usulan/{application}/penyaluran', [ProcessController::class, 'disbursementRequest'])->name('process.disb.request');
    Route::post('/penyaluran/{disbursement}/proses', [ProcessController::class, 'disbursementProcess'])->name('process.disb.process');
    Route::post('/penyaluran/{disbursement}/tolak', [ProcessController::class, 'disbursementReject'])->name('process.disb.reject');
    Route::post('/usulan/{application}/pengecualian', [ProcessController::class, 'exceptionRequest'])->name('process.exception.request');
    Route::post('/usulan/{application}/pengecualian/otorisasi', [ProcessController::class, 'exceptionDecide'])->name('process.exception.decide');
    Route::post('/usulan/{application}/barang', [ProcessController::class, 'goodsSave'])->name('process.goods.save');
    Route::post('/usulan/{application}/barang/serah', [ProcessController::class, 'goodsDeliver'])->name('process.goods.deliver');
    Route::post('/usulan/{application}/barang/aset', [ProcessController::class, 'goodsAsset'])->name('process.goods.asset');
    Route::post('/usulan/{application}/lpj', [ProcessController::class, 'reportSave'])->name('process.report.save');
    Route::post('/usulan/{application}/lpj/selesai-kegiatan', [ProcessController::class, 'activityDone'])->name('process.report.done');
    Route::post('/laporan-lpj/{report}/ajukan', [ProcessController::class, 'reportSubmit'])->name('process.report.submit');
    Route::post('/laporan-lpj/{report}/periksa', [ProcessController::class, 'reportReview'])->name('process.report.review');
    Route::post('/usulan/{application}/setoran', [ProcessController::class, 'fundReturn'])->name('process.return');

    // ---- monev, koreksi, keputusan, pagu
    Route::get('/monev', [MonevController::class, 'index'])->name('monev.index')->middleware('perm:monev,V');
    Route::post('/usulan/{application}/monev', [MonevController::class, 'store'])->name('monev.store');
    Route::post('/monev/{record}/tindak-lanjut', [MonevController::class, 'followUp'])->name('monev.followup');

    Route::get('/koreksi', [CorrectionController::class, 'index'])->name('corrections.index')->middleware('perm:koreksi_data,V');
    Route::post('/usulan/{application}/koreksi', [CorrectionController::class, 'store'])->name('corrections.store');
    Route::post('/koreksi/{correction}/putuskan', [CorrectionController::class, 'decide'])->name('corrections.decide');

    Route::get('/keputusan', [DecreeController::class, 'index'])->name('decrees.index')->middleware('perm:penetapan,V');
    Route::post('/keputusan', [DecreeController::class, 'store'])->name('decrees.store')->middleware('perm:penetapan,P');
    Route::get('/keputusan/{decree}/nominatif', [DecreeController::class, 'nominatif'])->name('decrees.nominatif')->middleware('perm:penetapan,V');

    Route::get('/pagu', [BudgetController::class, 'index'])->name('budget.index')->middleware('perm:anggaran,V');
    Route::post('/pagu', [BudgetController::class, 'store'])->name('budget.store')->middleware('perm:anggaran,F');

    // ---- laporan & cetak
    Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index')->middleware('perm:dashboard_laporan,V');
    Route::get('/laporan/{slug}', [ReportController::class, 'show'])->name('reports.show')->middleware('perm:dashboard_laporan,V');
    Route::get('/laporan/{slug}/ekspor/{format}', [ReportController::class, 'export'])->name('reports.export')->middleware('perm:dashboard_laporan,V');
    Route::get('/usulan/{application}/cetak/{template}', [PrintController::class, 'show'])->name('print');

    // ---- audit
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::post('/audit/verifikasi', [AuditController::class, 'verify'])->name('audit.verify');

    // ---- administrasi sistem
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index')->middleware('perm:kelola_pengguna,V');
        Route::post('/pengguna', [Admin\UserController::class, 'store'])->name('users.store')->middleware('perm:kelola_pengguna,F');
        Route::post('/pengguna/{user}/validasi', [Admin\UserController::class, 'validateAccount'])->name('users.validate')->middleware('perm:kelola_pengguna,F');
        Route::put('/pengguna/{user}', [Admin\UserController::class, 'update'])->name('users.update')->middleware('perm:kelola_pengguna,F');
        Route::post('/pengguna/{user}/reset', [Admin\UserController::class, 'reset'])->name('users.reset')->middleware('perm:kelola_pengguna,F');

        Route::get('/master/{entity}', [Admin\MasterController::class, 'index'])->name('master.index')->middleware('perm:data_master,V');
        Route::post('/master/{entity}', [Admin\MasterController::class, 'store'])->name('master.store')->middleware('perm:data_master,F');
        Route::put('/master/{entity}/{id}', [Admin\MasterController::class, 'update'])->name('master.update')->middleware('perm:data_master,F');

        Route::get('/konfigurasi', [Admin\SettingController::class, 'index'])->name('settings.index')->middleware('perm:konfigurasi,V');
        Route::put('/konfigurasi', [Admin\SettingController::class, 'update'])->name('settings.update')->middleware('perm:konfigurasi,F');

        Route::get('/akun-demo', [Admin\DemoAccountController::class, 'index'])->name('demo.index')->middleware('perm:kelola_pengguna,V');
        Route::get('/backup', [Admin\BackupController::class, 'index'])->name('backup.index')->middleware('perm:backup,V');
        Route::post('/backup', [Admin\BackupController::class, 'run'])->name('backup.run')->middleware('perm:backup,F');
        Route::post('/backup/{log}/verifikasi', [Admin\BackupController::class, 'verify'])->name('backup.verify')->middleware('perm:backup,F');
    });
});
