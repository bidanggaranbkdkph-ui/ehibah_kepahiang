<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\FiscalYear;
use App\Models\GrantApplication;
use App\Models\WorkflowSchedule;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StageScheduleService
{
    /**
     * Pastikan 9 tahapan tersedia untuk Tahun Anggaran terkait (Auto-seed jika belum ada)
     */
    public function ensureSchedulesForFiscalYear(int $fiscalYearId): Collection
    {
        $existing = WorkflowSchedule::where('fiscal_year_id', $fiscalYearId)
            ->orderBy('sequence')
            ->get()
            ->keyBy('stage_code');

        $yearObj = FiscalYear::find($fiscalYearId);
        $year = $yearObj ? (int) $yearObj->year : (int) date('Y');

        foreach (WorkflowSchedule::STAGES as $code => $meta) {
            if (!$existing->has($code)) {
                [$defaultStart, $defaultEnd] = $this->defaultDatesForStage($code, $year);

                $created = WorkflowSchedule::create([
                    'fiscal_year_id' => $fiscalYearId,
                    'stage_code'     => $code,
                    'stage_name'     => $meta['name'],
                    'sequence'       => $meta['sequence'],
                    'regulation_ref' => $meta['regulation_ref'],
                    'start_date'     => $defaultStart,
                    'end_date'       => $defaultEnd,
                    'control_mode'   => 'AUTO',
                ]);
                $existing->put($code, $created);
            }
        }

        return $existing->sortBy('sequence')->values();
    }

    /**
     * Memetakan Status tujuan (atau aksi) ke Kode Tahapan Bisnis
     */
    public function resolveStageCode(Status|string $targetStatusOrStage): ?string
    {
        $val = $targetStatusOrStage instanceof Status ? $targetStatusOrStage->value : $targetStatusOrStage;

        if (isset(WorkflowSchedule::STAGES[$val])) {
            return $val;
        }

        return match ($val) {
            Status::DRAFT->value,
            Status::SUBMITTED->value   => 'SUBMISSION',

            Status::ADMIN_CHECK->value,
            Status::TECH_CHECK->value,
            Status::RECOMMENDED->value,
            Status::RETURNED->value,
            Status::REJECTED->value    => 'VERIFICATION',

            Status::TAPD_REVIEW->value => 'TAPD_REVIEW',
            Status::APPROVED->value    => 'APPROVAL',
            Status::BUDGETED->value    => 'BUDGETING',
            Status::NPHD_SIGNED->value => 'NPHD',
            Status::DISBURSED->value   => 'DISBURSEMENT',
            Status::LPJ_REVIEW->value  => 'ACCOUNTABILITY',
            Status::CLOSED->value      => 'AUDIT',
            default                    => null,
        };
    }

    /**
     * Validasi apakah tahapan sedang dibuka; lempar ValidationException jika ditutup
     */
    public function assertStageOpen(int $fiscalYearId, Status|string $stageOrStatus): void
    {
        $stageCode = $this->resolveStageCode($stageOrStatus);
        if (!$stageCode) {
            return;
        }

        $schedules = $this->ensureSchedulesForFiscalYear($fiscalYearId)->keyBy('stage_code');
        /** @var WorkflowSchedule|null $schedule */
        $schedule = $schedules->get($stageCode);

        if ($schedule && !$schedule->isOpen()) {
            throw ValidationException::withMessages([
                'stage' => sprintf(
                    'Tahapan "%s" saat ini sedang DITUTUP (%s). Silakan hubungi Admin apabila diperlukan pembukaan jadwal tahapan.',
                    $schedule->stage_name,
                    $schedule->statusMessage()
                ),
            ]);
        }
    }

    /**
     * Cek status boolean tahapan untuk kebutuhan tampilan tombol di Blade View
     */
    public function isStageOpenForApplication(GrantApplication $app, string $stageCode): bool
    {
        $schedules = $this->ensureSchedulesForFiscalYear($app->fiscal_year_id)->keyBy('stage_code');
        $schedule = $schedules->get($stageCode);

        return $schedule ? $schedule->isOpen() : true;
    }

    /**
     * Default tanggal tahapan berdasarkan Tahun Anggaran sesuai regulasi daerah
     */
    private function defaultDatesForStage(string $code, int $year): array
    {
        return match ($code) {
            'SUBMISSION'     => ["{$year}-01-01 00:00:00", "{$year}-03-31 23:59:59"],
            'VERIFICATION'   => ["{$year}-03-01 00:00:00", "{$year}-05-31 23:59:59"],
            'TAPD_REVIEW'    => ["{$year}-06-01 00:00:00", "{$year}-08-31 23:59:59"],
            'APPROVAL'       => ["{$year}-09-01 00:00:00", "{$year}-11-30 23:59:59"],
            'BUDGETING'      => ["{$year}-11-01 00:00:00", "{$year}-12-31 23:59:59"],
            'NPHD'           => ["{$year}-01-02 00:00:00", "{$year}-10-31 23:59:59"],
            'DISBURSEMENT'   => ["{$year}-02-01 00:00:00", "{$year}-12-20 23:59:59"],
            'ACCOUNTABILITY' => ["{$year}-02-15 00:00:00", ($year + 1) . "-01-10 23:59:59"],
            'AUDIT'          => ["{$year}-01-01 00:00:00", ($year + 1) . "-03-31 23:59:59"],
            default          => [null, null],
        };
    }
}