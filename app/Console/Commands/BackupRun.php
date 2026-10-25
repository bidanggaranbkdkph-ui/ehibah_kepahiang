<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'ehibah:backup {type=FULL : DB | FILES | FULL} {--verify : uji keutuhan hasil backup}';

    protected $description = 'Backup database + berkas dokumen ke direktori backup (EHIBAH_BACKUP_PATH)';

    public function handle(BackupService $svc): int
    {
        $type = strtoupper($this->argument('type'));
        if (! in_array($type, ['DB', 'FILES', 'FULL'], true)) {
            $this->error('Jenis harus DB, FILES, atau FULL.');

            return self::INVALID;
        }
        $log = $svc->run($type);
        if ($log->status !== 'BERHASIL') {
            $this->error('Backup gagal: '.$log->message);

            return self::FAILURE;
        }
        $this->info("Backup $type berhasil: {$log->path} (".number_format($log->size_bytes / 1024, 1).' KB)');
        if ($this->option('verify')) {
            $ok = $svc->verify($log);
            $this->line($ok ? 'Verifikasi: OK' : 'Verifikasi GAGAL: '.$log->message);

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
