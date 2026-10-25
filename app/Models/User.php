<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret'];

    private ?array $roleCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_enabled_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /** @return string[] kode peran, mis. ['OPD'] */
    public function roleCodes(): array
    {
        return $this->roleCache ??= $this->roles()->pluck('code')->all();
    }

    public function hasRole(string ...$codes): bool
    {
        return count(array_intersect($codes, $this->roleCodes())) > 0;
    }

    public function forgetRoleCache(): void
    {
        $this->roleCache = null;
        $this->unsetRelation('roles');
    }

    /** Peran utama untuk tampilan & log (urutan prioritas sesuai konfigurasi). */
    public function primaryRole(): ?string
    {
        foreach (array_keys(config('ehibah.roles')) as $code) {
            if (in_array($code, $this->roleCodes(), true)) {
                return $code;
            }
        }

        return null;
    }

    public function roleLabel(): string
    {
        return config('ehibah.roles.'.$this->primaryRole(), '-');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function requiresMfa(): bool
    {
        return \App\Support\Setting::bool('security.mfa_enforced')
            && count(array_intersect($this->roleCodes(), config('ehibah.mfa_roles'))) > 0;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }
}
