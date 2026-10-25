<?php

namespace App\Console\Commands;

use App\Support\Permission;
use Illuminate\Console\Command;

class MatrixExport extends Command
{
    protected $signature = 'ehibah:matrix';

    protected $description = 'Cetak matriks hak akses (peran × modul) sebagai tabel Markdown dari config/ehibah.php';

    public function handle(): int
    {
        $roles = config('ehibah.roles');
        $sym = ['F' => '**F**', 'P' => '**P**', 'V' => 'V', 'N' => '–'];
        $this->line('| Modul / fitur | '.implode(' | ', array_map(fn ($r) => str_replace(' / ', '/', $r), $roles)).' |');
        $this->line('|---|'.str_repeat(':---:|', count($roles)));
        foreach (Permission::matrix() as $group => $rows) {
            $this->line("| **$group** |".str_repeat(' |', count($roles)));
            foreach ($rows as $r) {
                $this->line('| '.$r['label'].' | '.implode(' | ', array_map(fn ($c) => $sym[$r['levels'][$c]], array_keys($roles))).' |');
            }
        }

        return self::SUCCESS;
    }
}
