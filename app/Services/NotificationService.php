<?php

namespace App\Services;

use App\Models\GrantApplication;
use App\Models\User;
use App\Notifications\ApplicationNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    /**
     * Pengguna aktif yang berkepentingan pada satu usulan menurut peran.
     * PEMOHON/OPD dibatasi pada organisasi usulan; peran lintas OPD menerima seluruhnya.
     */
    public function recipients(GrantApplication $app, array $roles): Collection
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('code', $roles))
            ->get()
            ->filter(function (User $u) use ($app) {
                if ($u->hasRole('PEMOHON') && $u->organization_id === $app->applicant_org_id) {
                    return true;
                }
                if ($u->hasRole('OPD') && $u->organization_id === $app->opd_id) {
                    return true;
                }

                return (bool) array_intersect($u->roleCodes(), ['VERIFIKATOR', 'TAPD', 'BUPATI', 'BPKAD', 'INSPEKTORAT']);
            })
            ->filter(fn (User $u) => count(array_intersect($u->roleCodes(), $roles)) > 0)
            ->values();
    }

    public function send(GrantApplication $app, array $roles, string $title, string $message, string $level = 'info', ?int $exceptUserId = null): int
    {
        $users = $this->recipients($app, $roles)->reject(fn ($u) => $u->id === $exceptUserId);
        if ($users->isEmpty()) {
            return 0;
        }
        Notification::send($users, new ApplicationNotice($app, $title, $message, $level));

        return $users->count();
    }
}
