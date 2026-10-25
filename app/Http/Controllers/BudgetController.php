<?php

namespace App\Http\Controllers;

use App\Models\BudgetCeiling;
use App\Models\FiscalYear;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\GrantCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Data pagu hibah per OPD per tahun anggaran. */
class BudgetController extends Controller
{
    public function index(Request $request, GrantCalculator $calc)
    {
        $fy = FiscalYear::find($request->input('fy')) ?? FiscalYear::current();
        $rows = BudgetCeiling::with('opd')->where('fiscal_year_id', $fy?->id)->get()->map(fn ($c) => [
            'c' => $c, 'used' => $c->used(), 'remaining' => $c->remaining(), 'pct' => $calc->percent($c->used(), $c->ceiling),
            'confirmed' => (int) $c->allocations()->where('status', 'CONFIRMED')->sum('amount'),
        ]);

        return view('budget.index', [
            'fy' => $fy, 'years' => FiscalYear::orderByDesc('year')->get(), 'rows' => $rows,
            'opds' => Organization::where('type', 'OPD')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $d = $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id', 'opd_id' => 'required|exists:organizations,id',
            'ceiling' => 'required|integer|min:0', 'account_code' => 'nullable|string|max:40', 'dpa_ref' => 'nullable|string|max:80', 'notes' => 'nullable|string',
        ], [], ['ceiling' => 'Nilai pagu']);

        $existing = BudgetCeiling::where('fiscal_year_id', $d['fiscal_year_id'])->where('opd_id', $d['opd_id'])->first();
        if ($existing && $d['ceiling'] < $existing->used()) {
            throw ValidationException::withMessages(['ceiling' => 'Pagu baru tidak boleh lebih kecil dari dana yang sudah dialokasikan ('.\App\Support\Fmt::rupiah($existing->used()).').']);
        }
        $old = $existing?->only(['ceiling', 'account_code', 'dpa_ref']);
        $c = BudgetCeiling::updateOrCreate(
            ['fiscal_year_id' => $d['fiscal_year_id'], 'opd_id' => $d['opd_id']],
            ['ceiling' => $d['ceiling'], 'account_code' => $d['account_code'] ?? null, 'dpa_ref' => $d['dpa_ref'] ?? null, 'notes' => $d['notes'] ?? null, 'updated_by' => $request->user()->id]
        );
        $audit->record($existing ? 'CEILING_UPDATED' : 'CEILING_CREATED', $c, $old, $c->only(['ceiling', 'account_code', 'dpa_ref']));

        return back()->with('success', 'Pagu tersimpan.');
    }
}
