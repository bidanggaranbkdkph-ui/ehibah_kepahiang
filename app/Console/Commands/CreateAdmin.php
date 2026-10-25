<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'ehibah:create-admin {email} {name} {--org= : kode organisasi (mis. KOMINFO)} {--password= : kosongkan untuk dibuat acak}';

    protected $description = 'Buat akun Admin Sistem pertama (khusus instalasi baru / pemulihan akses)';

    public function handle(AuditService $audit): int
    {
        $email = mb_strtolower($this->argument('email'));
        if (User::where('email', $email)->exists()) {
            $this->error("Email $email sudah terdaftar.");

            return self::FAILURE;
        }
        $role = Role::where('code', 'ADMIN')->first();
        if (! $role) {
            $this->error('Peran ADMIN belum ada. Jalankan: php artisan db:seed --class=ReferenceSeeder');

            return self::FAILURE;
        }
        $org = $this->option('org') ? Organization::where('code', $this->option('org'))->first() : null;
        if ($this->option('org') && ! $org) {
            $this->error('Organisasi tidak ditemukan.');

            return self::FAILURE;
        }
        $password = $this->option('password') ?: Str::password(16);
        if (mb_strlen($password) < 10) {
            $this->error('Kata sandi minimal 10 karakter.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $this->argument('name'), 'email' => $email, 'password' => $password, 'organization_id' => $org?->id,
            'position' => 'Admin Sistem', 'account_status' => 'ACTIVE', 'is_active' => true,
        ]);
        $user->roles()->attach($role->id);
        $audit->record('ADMIN_CREATED_CLI', $user, null, ['email' => $email], 'SISTEM', null, $user);

        $this->info("Admin Sistem dibuat: $email");
        if (! $this->option('password')) {
            $this->warn("Kata sandi sementara (catat, ganti segera): $password");
        }

        return self::SUCCESS;
    }
}
