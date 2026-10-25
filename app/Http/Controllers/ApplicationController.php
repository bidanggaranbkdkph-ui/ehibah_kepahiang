<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Models\FiscalYear;
use App\Models\GrantApplication;
use App\Models\GrantType;
use App\Models\Organization;
use App\Models\Regulation;
use App\Services\AuditService;
use App\Services\DisbursementService;
use App\Services\DocumentChecklist;
use App\Services\DocumentService;
use App\Services\GrantCalculator;
use App\Services\WorkflowService;
use App\Support\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(
        private WorkflowService $wf,
        private DocumentChecklist $docs,
        private DocumentService $docService,
        private GrantCalculator $calc,
        private AuditService $audit,
        private DisbursementService $disb,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $q = GrantApplication::query()->visibleTo($user)->with(['applicant', 'opd', 'grantType', 'fiscalYear'])
            ->when($request->filled('q'), function ($w) use ($request) {
                $like = '%'.mb_strtolower($request->input('q')).'%';
                $w->where(fn ($x) => $x->whereRaw('lower(title) like ?', [$like])->orWhereRaw('lower(reg_number) like ?', [$like])
                    ->orWhereHas('applicant', fn ($o) => $o->whereRaw('lower(name) like ?', [$like])));
            })
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->input('status')))
            ->when($request->filled('fy'), fn ($w) => $w->where('fiscal_year_id', $request->input('fy')))
            ->when($request->filled('opd'), fn ($w) => $w->where('opd_id', $request->input('opd')))
            ->when($request->filled('type'), fn ($w) => $w->where('grant_type_id', $request->input('type')))
            ->latest('id');

        return view('applications.index', [
            'items' => $q->paginate(15)->withQueryString(),
            'years' => FiscalYear::orderByDesc('year')->get(), 'types' => GrantType::where('scope', 'HIBAH')->get(),
            'opds' => Organization::where('type', 'OPD')->orderBy('name')->get(), 'statuses' => Status::cases(),
        ]);
    }

    /** Halaman verifikasi: antrean kerja menurut peran. */
    public function queue(Request $request)
    {
        $user = $request->user();
        $apps = GrantApplication::query()->visibleTo($user)->with(['applicant', 'opd', 'grantType'])->get()
            ->filter(fn ($a) => $this->wf->availableActions($a, $user) !== [])
            ->sortBy('status_changed_at')->values();

        return view('applications.queue', ['apps' => $apps->groupBy(fn ($a) => $a->status->value), 'total' => $apps->count()]);
    }

    public function create(Request $request)
    {
        return view('applications.form', $this->formData($request, new GrantApplication(['funding_declaration' => false])));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validated($request);
        $fy = FiscalYear::where('status', 'BERJALAN')->orderByDesc('year')->firstOrFail();
        $org = $user->organization;
        if (! $org || $org->type !== 'LEMBAGA') {
            throw new AuthorizationException('Akun Anda tidak terhubung dengan lembaga pemohon.');
        }

        $app = DB::transaction(function () use ($data, $fy, $org, $user, $request) {
            $app = GrantApplication::create($data['fields'] + [
                'fiscal_year_id' => $fy->id, 'applicant_org_id' => $org->id, 'recipient_category_id' => $org->recipient_category_id,
                'created_by' => $user->id, 'status' => Status::DRAFT, 'status_changed_at' => now(),
            ]);
            $this->syncItems($app, $data['items']);
            $this->audit->record('APPLICATION_CREATED', $app, null, ['title' => $app->title, 'amount' => $app->amount_requested], 'PROSES', $app->id, $user);

            return $app;
        });

        return redirect()->route('applications.show', $app)->with('success', 'Draf usulan tersimpan. Lengkapi dokumen lalu ajukan.');
    }

    public function edit(Request $request, GrantApplication $application)
    {
        $this->authorizeEdit($application);

        return view('applications.form', $this->formData($request, $application->load(['items'])));
    }

    public function update(Request $request, GrantApplication $application)
    {
        $this->authorizeEdit($application);
        $data = $this->validated($request);

        DB::transaction(function () use ($application, $data, $request) {
            $app = GrantApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ((int) $request->input('lock_version') !== (int) $app->lock_version) {
                throw ValidationException::withMessages(['title' => 'Data usulan telah berubah di sesi lain. Muat ulang halaman.']);
            }
            $old = $app->only(['title', 'amount_requested', 'purpose']);
            $app->update($data['fields']);
            $this->syncItems($app, $data['items']);
            $app->increment('lock_version');
            $this->audit->record('APPLICATION_UPDATED', $app, $old, $app->only(['title', 'amount_requested', 'purpose']), 'PROSES', $app->id, $request->user());
        });

        return redirect()->route('applications.show', $application)->with('success', 'Perubahan tersimpan.');
    }

    public function show(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $user = $request->user();
        $app = $application->load([
            'applicant', 'opd', 'grantType', 'category', 'regulation', 'fiscalYear', 'items', 'verifications.verifier', 'recommendation',
            'tapdReview.reviewer', 'decision.decree', 'allocation.ceiling', 'nphds', 'disbursements', 'goodsDelivery.items', 'reports.expenses',
            'fundReturns', 'warnings', 'monitoring.officer', 'histories.user', 'corrections.requester',
        ]);

        $actions = $this->wf->availableActions($app, $user);
        $blockers = [];
        foreach ($actions as $key => $def) {
            $blockers[$key] = $this->wf->blockers($app, $key);
        }

        $stages = ['PENGAJUAN'];
        $s = $app->status;
        if ($s->order() >= Status::BUDGETED->order()) {
            $stages[] = 'NPHD';
        }
        if ($s->order() >= Status::NPHD_SIGNED->order()) {
            $stages[] = $app->isMoney() ? 'PENCAIRAN' : 'PENYERAHAN';
        }
        if ($s->order() >= Status::DISBURSED->order()) {
            $stages[] = 'LPJ';
        }

        $checklists = [];
        foreach ($stages as $st) {
            if ($st === 'LPJ') {
                foreach ($app->reports as $r) {
                    $checklists['LPJ:'.$r->id] = ['stage' => 'LPJ', 'related' => ['report', $r->id], 'label' => $r->report_type === 'AKHIR' ? 'LPJ akhir' : 'Laporan tahap I',
                        'items' => $this->docs->items($app, 'LPJ', ['report', $r->id]), 'progress' => $this->docs->progress($app, 'LPJ', ['report', $r->id])];
                }

                continue;
            }
            $checklists[$st] = ['stage' => $st, 'related' => null, 'label' => DocumentChecklist::STAGES[$st],
                'items' => $this->docs->items($app, $st), 'progress' => $this->docs->progress($app, $st)];
        }

        $canUpload = [];
        foreach (array_keys(DocumentChecklist::STAGES) as $st) {
            $canUpload[$st] = $this->docService->canUpload($user, $app, $st);
        }

        $isOpd = $user->hasRole('OPD') && $user->organization_id === $app->opd_id;
        $isApplicant = $user->hasRole('PEMOHON') && $user->organization_id === $app->applicant_org_id;

        return view('applications.show', [
            'app' => $app, 'actions' => $actions, 'blockers' => $blockers, 'checklists' => $checklists, 'canUpload' => $canUpload,
            'criteria' => WorkflowService::CRITERIA, 'techScores' => WorkflowService::TECH_SCORES,
            'plan' => ($app->isMoney() && $s->order() >= Status::NPHD_SIGNED->order() && $s !== Status::REJECTED) ? $this->disb->plan($app) : null,
            'techVerification' => $app->verifications->where('stage', 'TEKNIS')->where('round', $app->revision_no)->last(),
            'decrees' => \App\Models\Decree::where('fiscal_year_id', $app->fiscal_year_id)->orderByDesc('decree_date')->get(),
            'signers' => \App\Models\User::where('organization_id', $app->opd_id)->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('code', 'OPD'))->where('position', 'like', 'Kepala%')->get(),
            'ceilingInfo' => ($c = $app->allocation?->ceiling ?? \App\Models\BudgetCeiling::where('fiscal_year_id', $app->fiscal_year_id)->where('opd_id', $app->opd_id)->first())
                ? ['ceiling' => $c->ceiling, 'used' => $c->used(), 'remaining' => $c->remaining()] : null,
            'perm' => fn (string $f, string $l = 'V') => Permission::allows($user, $f, $l),
            'isOpd' => $isOpd, 'isApplicant' => $isApplicant,
            'correctionFields' => \App\Services\CorrectionService::fields(),
            'daysLate' => $this->calc->daysLate($app),
            'dueSoon' => $app->lpj_due_date && ! $app->lpj_overdue && $s === Status::DISBURSED ? (int) now()->startOfDay()->diffInDays($app->lpj_due_date, false) : null,
        ]);
    }

    // ------------------------------------------------------------------
    private function authorizeEdit(GrantApplication $app): void
    {
        $user = auth()->user();
        if (! $user->hasRole('PEMOHON') || $user->organization_id !== $app->applicant_org_id) {
            throw new AuthorizationException('Hanya pemohon pemilik usulan yang dapat mengubahnya.');
        }
        if (! $app->status->isEditableByApplicant()) {
            throw new AuthorizationException('Usulan terkunci pada status "'.$app->status->label().'". Gunakan jalur koreksi data bila perlu.');
        }
    }

    private function formData(Request $request, GrantApplication $app): array
    {
        return [
            'app' => $app,
            'types' => GrantType::where('scope', 'HIBAH')->where('is_active', true)->get(),
            'opds' => Organization::where('type', 'OPD')->where('is_active', true)->whereNotIn('code', ['BPKAD', 'INSPEKTORAT', 'KOMINFO', 'SETDA'])->orderBy('name')->get(),
            'regulations' => Regulation::where('is_grant_basis', true)->where('status', 'AKTIF')->get(),
            'org' => $request->user()->organization,
        ];
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'grant_type_id' => 'required|exists:grant_types,id',
            'opd_id' => 'required|exists:organizations,id',
            'regulation_id' => 'required|exists:regulations,id',
            'title' => 'required|string|max:250',
            'background' => 'required|string', 'purpose' => 'required|string', 'target' => 'required|string',
            'program_activity' => 'required|string', 'location' => 'required|string|max:250',
            'activity_start' => 'required|date', 'activity_end' => 'required|date|after_or_equal:activity_start',
            'is_recurring' => 'nullable|boolean', 'recurring_reason' => 'nullable|string|required_if:is_recurring,1',
            'funding_declaration' => 'nullable|boolean',
            'committee' => 'required|array|min:1', 'committee.*.nama' => 'required|string|max:150', 'committee.*.jabatan' => 'required|string|max:100',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string|max:250',
            'items.*.quantity' => 'required|numeric|min:0.01', 'items.*.unit' => 'required|string|max:30', 'items.*.unit_price' => 'required|integer|min:1',
        ], [
            'committee.required' => 'Susunan panitia minimal 1 orang.', 'items.required' => 'RAB / daftar barang minimal 1 baris.',
            'items.*.unit_price.integer' => 'Harga satuan harus berupa angka rupiah bulat tanpa titik/koma.',
        ], [
            'grant_type_id' => 'Jenis hibah', 'opd_id' => 'OPD pemilik urusan', 'regulation_id' => 'Dasar hukum', 'title' => 'Judul',
            'background' => 'Latar belakang', 'purpose' => 'Maksud dan tujuan', 'target' => 'Sasaran', 'program_activity' => 'Program/kegiatan',
            'activity_start' => 'Tanggal mulai', 'activity_end' => 'Tanggal selesai', 'recurring_reason' => 'Alasan pengecualian',
        ]);

        $type = GrantType::findOrFail($d['grant_type_id']);
        if ($type->scope !== 'HIBAH' || ! $type->is_active) {
            throw ValidationException::withMessages(['grant_type_id' => 'Jenis bantuan ini bukan hibah dan tidak dapat diajukan melalui E-Hibah.']);
        }

        $items = collect($d['items'])->values()->map(fn ($i) => $i + ['subtotal' => $this->calc->lineSubtotal((float) $i['quantity'], (int) $i['unit_price'])]);

        $fields = collect($d)->only([
            'grant_type_id', 'opd_id', 'regulation_id', 'title', 'background', 'purpose', 'target', 'program_activity', 'location',
            'activity_start', 'activity_end', 'recurring_reason',
        ])->all();
        $fields['committee'] = array_values($d['committee']);
        $fields['is_recurring'] = (bool) ($d['is_recurring'] ?? false);
        $fields['funding_declaration'] = (bool) ($d['funding_declaration'] ?? false);
        $fields['amount_requested'] = (int) $items->sum('subtotal');

        return ['fields' => $fields, 'items' => $items->all()];
    }

    private function syncItems(GrantApplication $app, array $items): void
    {
        $app->items()->delete();
        foreach ($items as $i => $row) {
            $app->items()->create([
                'description' => $row['description'], 'quantity' => $row['quantity'], 'unit' => $row['unit'],
                'unit_price' => $row['unit_price'], 'subtotal' => $row['subtotal'], 'sort_order' => $i,
            ]);
        }
    }
}
