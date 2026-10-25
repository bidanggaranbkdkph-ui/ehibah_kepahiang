<?php

namespace App\Http\Controllers;

use App\Models\DataCorrection;
use App\Models\GrantApplication;
use App\Services\CorrectionService;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    public function __construct(private CorrectionService $svc)
    {
    }

    public function index(Request $request)
    {
        $visible = GrantApplication::query()->visibleTo($request->user())->select('grant_applications.id');
        $items = DataCorrection::with(['application', 'requester', 'decider'])->whereIn('application_id', $visible)
            ->orderByRaw("case when status = 'DIAJUKAN' then 0 else 1 end")->latest('id')->paginate(15);

        return view('corrections.index', ['items' => $items, 'svc' => $this->svc]);
    }

    public function store(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $d = $request->validate([
            'target' => 'required|string', 'new_value' => 'required|string|max:2000', 'reason' => 'required|string',
        ]);
        // target = "entity:entityId:field"
        [$entity, $id, $field] = array_pad(explode(':', $d['target'], 3), 3, null);
        $this->svc->request($application, $request->user(), (string) $entity, (int) $id, (string) $field, $d['new_value'], $d['reason']);

        return back()->with('success', 'Permohonan koreksi diajukan dan menunggu persetujuan pihak berwenang.');
    }

    public function decide(Request $request, DataCorrection $correction)
    {
        $this->authorizeView($correction->application);
        $this->svc->decide($correction, $request->user(), $request->input('decision') === 'approve', $request->input('decision_note'));

        return back()->with('success', 'Keputusan koreksi tercatat.');
    }
}
