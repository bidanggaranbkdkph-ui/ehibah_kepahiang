<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use Illuminate\Console\Command;

class AuditVerify extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verifikasi keutuhan rantai hash audit trail (deteksi baris yang diubah/dihapus/disisipkan)';

    public function handle(AuditService $audit): int
    {
        $broken = $audit->verifyChain();
        if ($broken) {
            $this->error("RANTAI AUDIT RUSAK pada baris #{$broken['id']}: {$broken['reason']}");

            return self::FAILURE;
        }
        $this->info('Rantai audit utuh.');

        return self::SUCCESS;
    }
}
