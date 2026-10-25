<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backup', ['logs' => BackupLog::latest('id')->limit(30)->get(), 'path' => config('ehibah.backup_path')]);
    }

    public function run(Request $request, BackupService $svc)
    {
        $request->validate(['type' => 'required|in:DB,FILES,FULL']);
        $log = $svc->run($request->input('type'), $request->user());

        return back()->with($log->status === 'BERHASIL' ? 'success' : 'error', $log->status === 'BERHASIL'
            ? "Backup {$log->type} selesai ({$log->path})." : 'Backup gagal: '.$log->message);
    }

    public function verify(BackupLog $log, BackupService $svc)
    {
        $ok = $svc->verify($log);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Berkas backup utuh (checksum cocok, arsip dapat dibuka).' : 'Verifikasi GAGAL: '.$log->message);
    }
}
