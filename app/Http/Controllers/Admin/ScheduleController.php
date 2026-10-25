<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Models\WorkflowSchedule;
use App\Services\AuditService;
use App\Services\StageScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request, StageScheduleService $scheduleService)
    {
        $fiscalYears = FiscalYear::orderByDesc('year')->get();
        $selectedYearId = (int) $request->query('fiscal_year_id', $fiscalYears->first()?->id);

        $schedules = $selectedYearId
            ? $scheduleService->ensureSchedulesForFiscalYear($selectedYearId)
            : collect();

        return view('admin.schedules', compact('fiscalYears', 'selectedYearId', 'schedules'));
    }

    public function update(Request $request, WorkflowSchedule $schedule, AuditService $audit)
    {
        $validated = $request->validate([
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'control_mode'   => ['required', 'in:AUTO,FORCE_OPEN,FORCE_CLOSED'],
            'regulation_ref' => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $old = $schedule->only(['start_date', 'end_date', 'control_mode', 'regulation_ref', 'notes']);

        $schedule->update([
            ...$validated,
            'updated_by' => auth()->id(),
        ]);

        $audit->log('UPDATE_STAGE_SCHEDULE', 'WorkflowSchedule', $schedule->id, $old, $schedule->fresh()->toArray());

        return back()->with('success', "Jadwal tahapan [{$schedule->stage_name}] berhasil diperbarui.");
    }

    public function quickToggle(Request $request, WorkflowSchedule $schedule, AuditService $audit)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:AUTO,FORCE_OPEN,FORCE_CLOSED'],
        ]);

        $oldMode = $schedule->control_mode;
        $schedule->update([
            'control_mode' => $validated['mode'],
            'updated_by'   => auth()->id(),
        ]);

        $audit->log('TOGGLE_STAGE_SCHEDULE', 'WorkflowSchedule', $schedule->id, ['control_mode' => $oldMode], ['control_mode' => $validated['mode']]);

        $label = match ($validated['mode']) {
            'FORCE_OPEN'   => 'DIBUKA MANUAL',
            'FORCE_CLOSED' => 'DITUTUP MANUAL',
            default        => 'DIKEMBALIKAN KE JADWAL OTOMATIS',
        };

        return back()->with('success', "Tahapan [{$schedule->stage_name}] berhasil {$label}.");
    }
}