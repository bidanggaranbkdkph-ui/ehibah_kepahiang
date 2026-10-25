<?php

namespace App\Http\Controllers;

use App\Models\GrantApplication;
use App\Models\MonitoringRecord;
use App\Services\AccountabilityService;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class MonevController extends Controller
{
    public function index(Request $request)
    {
        $visible = GrantApplication::query()->visibleTo($request->user())->select('grant_applications.id');
        $records = MonitoringRecord::with(['application.applicant', 'officer'])->whereIn('application_id', $visible)
            ->when($request->input('status'), fn ($q, $s) => $q->where('follow_up_status', $s))->latest('visited_at')->paginate(15)->withQueryString();

        return view('monev.index', ['records' => $records]);
    }

    public function store(Request $request, GrantApplication $application, AccountabilityService $acc)
    {
        $this->authorizeView($application);
        $acc->recordMonitoring($application, $request->user(), $request->all());

        return back()->with('success', 'Monitoring tercatat dan disampaikan ke Bupati dengan tembusan Inspektorat.');
    }

    public function followUp(Request $request, MonitoringRecord $record, AuditService $audit)
    {
        $this->authorizeView($record->application);
        $this->requirePerm('monev', 'P');
        $u = $request->user();
        if ($u->hasRole('OPD') && ! $u->hasRole('INSPEKTORAT') && $u->organization_id !== $record->application->opd_id) {
            throw new AuthorizationException('Monitoring di luar OPD Anda.');
        }
        $request->validate(['follow_up' => 'nullable|string']);
        $record->update(['follow_up_status' => 'SELESAI', 'follow_up' => $request->input('follow_up', $record->follow_up)]);
        $audit->record('FOLLOWUP_DONE', $record, null, ['status' => 'SELESAI'], 'PROSES', $record->application_id, $u);

        return back()->with('success', 'Tindak lanjut ditandai selesai.');
    }
}
