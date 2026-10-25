<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\BudgetCeiling;
use App\Models\DataCorrection;
use App\Models\FiscalYear;
use App\Models\GrantApplication;
use App\Models\User;
use App\Services\GrantCalculator;
use App\Services\WorkflowService;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private GrantCalculator $calc, private WorkflowService $wf)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('ADMIN') && count($user->roleCodes()) === 1) {
            return view('dashboard.admin', [
                'pending' => User::where('account_status', 'PENDING')->latest()->limit(8)->get(),
                'counts' => [
                    'pengguna' => User::where('is_active', true)->count(),
                    'menunggu' => User::where('account_status', 'PENDING')->count(),
                    'terkunci' => User::where('locked_until', '>', now())->count(),
                    'mfa' => User::whereNotNull('mfa_enabled_at')->count(),
                ],
                'lastBackup' => \App\Models\BackupLog::latest('id')->first(),
            ]);
        }

        $years = FiscalYear::orderByDesc('year')->get();
        $fy = $years->firstWhere('id', (int) $request->input('fy')) ?? FiscalYear::current();
        $scope = fn () => GrantApplication::query()->visibleTo($user)->when($fy, fn ($q) => $q->where('fiscal_year_id', $fy->id));

        $submitted = $scope()->where('status', '!=', Status::DRAFT->value);
        $requested = (clone $submitted)->sum('amount_requested');
        $approved = (clone $submitted)->whereNotNull('amount_approved')->sum('amount_approved');
        $disbursed = (int) DB::table('disbursements')->where('status', 'DISALURKAN')
            ->whereIn('application_id', $scope()->select('grant_applications.id'))->sum('amount');
        $overdue = $scope()->where('lpj_overdue', true)->count();

        $byStatus = $scope()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $byOpd = $scope()->join('organizations as o', 'o.id', '=', 'grant_applications.opd_id')
            ->where('status', '!=', Status::DRAFT->value)
            ->select('o.name', DB::raw('count(*) as n'), DB::raw('sum(amount_requested) as requested'), DB::raw('coalesce(sum(amount_approved),0) as approved'))
            ->groupBy('o.name')->orderByDesc('n')->limit(8)->get();

        // antrean tindakan: usulan yang menunggu peran pengguna ini
        $tasks = $scope()->with(['applicant', 'grantType'])->get()
            ->filter(fn (GrantApplication $a) => $this->wf->availableActions($a, $user) !== [] || $this->awaiting($a, $user))
            ->sortBy('status_changed_at')->take(8)->values();

        $ceilings = null;
        if (Permission::allows($user, 'anggaran', 'V') && $fy) {
            $ceilings = BudgetCeiling::with('opd')->where('fiscal_year_id', $fy->id)->get()
                ->map(fn ($c) => ['opd' => $c->opd->name, 'ceiling' => $c->ceiling, 'used' => $c->used(), 'pct' => $this->calc->percent($c->used(), $c->ceiling)])
                ->when($user->hasRole('OPD') && ! $user->hasRole('TAPD', 'BPKAD', 'INSPEKTORAT'), fn ($c) => $c->where('opd', $user->organization?->name))
                ->values();
        }

        $monthly = $scope()->where('status', '!=', Status::DRAFT->value)->whereNotNull('submitted_at')->get()
            ->groupBy(fn ($a) => $a->submitted_at->format('Y-m'))->map->count()->sortKeys();

        return view('dashboard.index', [
            'fy' => $fy, 'years' => $years,
            'kpi' => [
                'usulan' => (clone $submitted)->count(), 'nilai_usulan' => $requested, 'ditetapkan' => $approved,
                'realisasi' => $disbursed, 'realisasi_pct' => $this->calc->percent($disbursed, max($approved, 1)),
                'tunggakan' => $overdue,
            ],
            'byStatus' => $byStatus, 'byOpd' => $byOpd, 'tasks' => $tasks, 'ceilings' => $ceilings, 'monthly' => $monthly,
            'corrections' => Permission::allows($user, 'koreksi_data', 'P')
                ? DataCorrection::where('status', 'DIAJUKAN')->count() : null,
            'isApplicant' => $user->hasRole('PEMOHON'),
        ]);
    }

    /** Peran pengguna termasuk yang memegang status usulan (untuk antrean meski tombol belum aktif). */
    private function awaiting(GrantApplication $a, User $u): bool
    {
        return ! $a->status->isTerminal() && count(array_intersect($a->status->responsibleRoles(), $u->roleCodes())) > 0
            && ($u->hasRole('PEMOHON') ? $u->organization_id === $a->applicant_org_id : ($u->hasRole('OPD') ? $u->organization_id === $a->opd_id : true));
    }
}
