<?php

namespace App\Console\Commands;

use App\Services\LpjMonitor;
use Illuminate\Console\Command;

class LpjReminders extends Command
{
    protected $signature = 'ehibah:lpj-reminders';

    protected $description = 'Kirim pengingat LPJ, tandai tunggakan, dan terbitkan surat peringatan (Perbup Pasal 19)';

    public function handle(LpjMonitor $monitor): int
    {
        $r = $monitor->run();
        $this->info("Pengingat: {$r['reminders']}, tunggakan baru: {$r['overdue']}, surat peringatan: {$r['warnings']}");

        return self::SUCCESS;
    }
}
