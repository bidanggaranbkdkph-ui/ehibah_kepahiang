<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Models\GrantType;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\ReportService;
use App\Support\Permission;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports, private AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $catalog = collect(ReportService::CATALOG)->when(! Permission::allows($request->user(), 'audit_trail', 'V'), fn ($c) => $c->except('audit'));

        return view('reports.index', ['catalog' => $catalog]);
    }

    public function show(Request $request, string $slug)
    {
        $data = $this->reports->build($slug, $request->user(), $request);

        return view('reports.show', [
            'data' => $data, 'svc' => $this->reports, 'slug' => $slug,
            'years' => FiscalYear::orderByDesc('year')->get(), 'types' => GrantType::where('scope', 'HIBAH')->get(),
            'opds' => Organization::where('type', 'OPD')->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, string $slug, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $data = $this->reports->build($slug, $request->user(), $request);
        if ($format === 'pdf' && count($data['rows']) > 1500) {
            return back()->with('error', 'Laporan terlalu besar untuk PDF ('.count($data['rows']).' baris). Persempit filter atau gunakan ekspor Excel.');
        }
        $this->audit->access('REPORT_EXPORTED', $request->user(), ['report' => $slug, 'format' => $format, 'rows' => count($data['rows'])]);

        return $format === 'xlsx' ? $this->reports->excel($data, $slug) : $this->reports->pdf($data);
    }
}
