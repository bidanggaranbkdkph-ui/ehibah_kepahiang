<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);

        // Data contoh hanya untuk lingkungan non-produksi (php artisan db:seed --class=DemoSeeder untuk memaksa).
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
