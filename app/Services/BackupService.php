<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backup berkala: dump database (pg_dump -Fc untuk PostgreSQL, salinan berkas untuk SQLite),
 * arsip berkas dokumen privat, manifest SHA-256, retensi otomatis, dan uji keutuhan.
 * Simpan salinan backup di lokasi terpisah dari server aplikasi (lihat docs/OPERASIONAL.md).
 */
class BackupService
{
    public function __construct(private AuditService $audit)
    {
    }

    public function run(string $type, ?User $by = null): BackupLog
    {
        $dir = config('ehibah.backup_path');
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Ymd_His');
        $log = BackupLog::create(['type' => $type, 'status' => 'BERJALAN', 'started_at' => now(), 'initiated_by' => $by?->id]);

        try {
            $parts = [];
            if (in_array($type, ['DB', 'FULL'], true)) {
                $parts['database'] = $this->dumpDatabase($dir, $stamp);
            }
            if (in_array($type, ['FILES', 'FULL'], true)) {
                $parts['files'] = $this->zipFiles($dir, $stamp);
            }

            // satu paket + manifest checksum
            $manifest = ['created_at' => now()->toIso8601String(), 'type' => $type, 'app' => config('app.name'), 'parts' => []];
            foreach ($parts as $name => $path) {
                $manifest['parts'][$name] = ['file' => basename($path), 'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)];
            }
            $pkg = "$dir/ehibah_{$type}_$stamp.zip";
            $zip = new ZipArchive;
            if ($zip->open($pkg, ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('Tidak dapat membuat paket backup.');
            }
            foreach ($parts as $path) {
                $zip->addFile($path, basename($path));
            }
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            $zip->close();
            foreach ($parts as $path) {
                @unlink($path);
            }

            $log->update([
                'status' => 'BERHASIL', 'path' => $pkg, 'size_bytes' => filesize($pkg), 'sha256' => hash_file('sha256', $pkg), 'finished_at' => now(),
            ]);
            $this->prune($dir);
        } catch (\Throwable $e) {
            $log->update(['status' => 'GAGAL', 'message' => $e->getMessage(), 'finished_at' => now()]);
        }
        $this->audit->record('BACKUP_'.$log->status, $log, null, ['type' => $type, 'size' => $log->size_bytes], 'SISTEM', null, $by);

        return $log;
    }

    private function dumpDatabase(string $dir, string $stamp): string
    {
        $conn = config('database.default');
        $cfg = config("database.connections.$conn");

        if ($cfg['driver'] === 'pgsql') {
            $out = "$dir/db_$stamp.dump";
            $p = new Process([
                env('PG_DUMP_BINARY', 'pg_dump'), '-Fc', '--no-owner', '-h', $cfg['host'], '-p', (string) $cfg['port'],
                '-U', $cfg['username'], '-d', $cfg['database'], '-f', $out,
            ], null, ['PGPASSWORD' => $cfg['password']]);
            $p->setTimeout(1800)->run();
            if (! $p->isSuccessful() || ! is_file($out) || filesize($out) === 0) {
                throw new \RuntimeException('pg_dump gagal: '.trim($p->getErrorOutput()));
            }

            return $out;
        }
        if ($cfg['driver'] === 'sqlite' && $cfg['database'] !== ':memory:') {
            $out = "$dir/db_$stamp.sqlite";
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($out));

            return $out;
        }
        throw new \RuntimeException('Driver database tidak didukung untuk backup: '.$cfg['driver']);
    }

    private function zipFiles(string $dir, string $stamp): string
    {
        $out = "$dir/files_$stamp.zip";
        $zip = new ZipArchive;
        $zip->open($out, ZipArchive::CREATE);
        $root = Storage::disk('local')->path('applications');
        if (is_dir($root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $zip->addFile($file->getPathname(), 'applications/'.ltrim(str_replace([$root, '\\'], ['', '/'], $file->getPathname()), '/'));
            }
        }
        if ($zip->numFiles === 0) {
            $zip->addFromString('README.txt', 'Belum ada berkas dokumen.');
        }
        $zip->close();

        return $out;
    }

    /** Uji keutuhan: checksum paket, arsip dapat dibuka, checksum tiap bagian sesuai manifest. */
    public function verify(BackupLog $log): bool
    {
        $msg = null;
        if (! $log->path || ! is_file($log->path)) {
            $msg = 'Berkas backup tidak ditemukan.';
        } elseif (hash_file('sha256', $log->path) !== $log->sha256) {
            $msg = 'Checksum paket tidak cocok (berkas berubah/rusak).';
        } else {
            $zip = new ZipArchive;
            if ($zip->open($log->path) !== true) {
                $msg = 'Arsip tidak dapat dibuka.';
            } else {
                $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
                foreach ($manifest['parts'] ?? [] as $name => $part) {
                    $content = $zip->getFromName($part['file']);
                    if ($content === false || hash('sha256', $content) !== $part['sha256']) {
                        $msg = "Bagian '$name' tidak cocok dengan manifest.";
                        break;
                    }
                }
                $zip->close();
            }
        }
        $log->update(['verified_at' => $msg ? null : now(), 'message' => $msg]);
        $this->audit->record($msg ? 'BACKUP_VERIFY_FAILED' : 'BACKUP_VERIFIED', $log, null, ['message' => $msg], 'SISTEM');

        return $msg === null;
    }

    private function prune(string $dir): void
    {
        $limit = now()->subDays(Setting::int('backup.retention_days'))->timestamp;
        foreach (glob("$dir/ehibah_*.zip") ?: [] as $f) {
            if (filemtime($f) < $limit) {
                @unlink($f);
            }
        }
    }
}
