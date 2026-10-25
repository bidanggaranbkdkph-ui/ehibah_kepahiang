<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\GrantApplication;
use App\Models\WarningLetter;
use App\Support\Fmt;
use App\Support\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Pemantau tenggat LPJ (Perbup Pasal 19): pengingat sebelum tenggat, penandaan tunggakan,
 * dan surat peringatan berjenjang oleh SKPD. Dijalankan harian oleh scheduler.
 */
class LpjMonitor
{
    public function __construct(private NotificationService $notifier, private AuditService $audit)
    {
    }

    /** @return array{reminders:int, overdue:int, warnings:int} */
    public function run(?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $out = ['reminders' => 0, 'overdue' => 0, 'warnings' => 0];
        $reminderDays = (array) Setting::get('lpj.reminder_days');
        $interval = max(1, Setting::int('lpj.warning_interval_days'));

        GrantApplication::query()->where('status', Status::DISBURSED->value)->whereNotNull('lpj_due_date')
            ->with(['applicant', 'opd'])->get()->each(function (GrantApplication $a) use ($today, $reminderDays, $interval, &$out) {
                $days = (int) $today->diffInDays($a->lpj_due_date->copy()->startOfDay(), false);

                if ($days >= 0 && in_array($days, $reminderDays, true)) {
                    // satu pengingat per usulan per ambang hari
                    if (Cache::add("lpj-remind:{$a->id}:$days", 1, now()->addDays(2))) {
                        $this->notifier->send($a, ['PEMOHON', 'OPD'], $days === 0 ? 'LPJ jatuh tempo hari ini' : "LPJ jatuh tempo $days hari lagi",
                            'Tenggat LPJ '.($a->reg_number).' adalah '.Fmt::date($a->lpj_due_date).'. Segera susun dan ajukan LPJ.', $days <= 1 ? 'warning' : 'info');
                        $out['reminders']++;
                    }
                }

                if ($days < 0) {
                    if (! $a->lpj_overdue) {
                        $a->update(['lpj_overdue' => true]);
                        $this->audit->record('LPJ_OVERDUE', $a, null, ['due' => $a->lpj_due_date->toDateString()], 'SISTEM', $a->id);
                        $this->notifier->send($a, ['PEMOHON', 'OPD', 'INSPEKTORAT'], 'LPJ melewati tenggat', 'LPJ '.$a->reg_number.' melewati tenggat '.Fmt::date($a->lpj_due_date).'.', 'danger');
                        $out['overdue']++;
                    }
                    $last = WarningLetter::where('application_id', $a->id)->latest('id')->first();
                    if (! $last || $last->letter_date->copy()->startOfDay()->diffInDays($today) >= $interval) {
                        $level = ($last?->level ?? 0) + 1;
                        $w = WarningLetter::create([
                            'application_id' => $a->id, 'level' => $level, 'letter_no' => sprintf('SP-%d/%s', $level, $a->reg_number),
                            'letter_date' => $today, 'lpj_due_date' => $a->lpj_due_date,
                            'notes' => 'Peringatan otomatis keterlambatan LPJ (Perbup Pasal 19).',
                        ]);
                        $this->audit->record('LPJ_WARNING_ISSUED', $w, null, ['level' => $level], 'SISTEM', $a->id);
                        $this->notifier->send($a, ['PEMOHON', 'OPD'], "Surat peringatan ke-$level — LPJ terlambat",
                            'Segera sampaikan LPJ '.$a->reg_number.'. Keterlambatan '.abs($days).' hari.', 'danger');
                        $out['warnings']++;
                    }
                }
            });

        return $out;
    }
}
