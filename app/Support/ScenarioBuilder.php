<?php

namespace App\Support;

use App\Enums\Status;
use App\Models\ApplicationDocument;
use App\Models\BudgetCeiling;
use App\Models\Decree;
use App\Models\FiscalYear;
use App\Models\GrantApplication;
use App\Models\GrantType;
use App\Models\Organization;
use App\Models\RecipientCategory;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountabilityService;
use App\Services\DisbursementService;
use App\Services\DocumentChecklist;
use App\Services\DocumentService;
use App\Services\GoodsService;
use App\Services\ProcessService;
use App\Services\WorkflowService;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Str;

/**
 * Membangun usulan hibah pada status tertentu LEWAT layanan yang sebenarnya
 * (bukan insert mentah), sehingga seluruh guard ikut teruji.
 * Dipakai oleh DemoSeeder dan test otomatis. Memerlukan ReferenceSeeder.
 */
class ScenarioBuilder
{
    /** @var array<string, User> */
    public array $u = [];

    public Organization $opd;

    public Organization $applicant;

    public FiscalYear $fy;

    private WorkflowService $wf;

    public function __construct()
    {
        $this->wf = app(WorkflowService::class);
        $this->fy = FiscalYear::where('year', 2026)->firstOrFail();
        $this->bootstrap();
    }

    public static function password(): string
    {
        return env('DEMO_PASSWORD', 'EHibah#Demo2026');
    }

    // ------------------------------------------------------------------ aktor
    private function bootstrap(): void
    {
        $this->opd = $this->org('DISDIKBUD', 'Dinas Pendidikan dan Kebudayaan', 'OPD');
        $bpkad = $this->org('BPKAD', 'Badan Pengelolaan Keuangan dan Aset Daerah', 'OPD');
        $insp = $this->org('INSPEKTORAT', 'Inspektorat Daerah', 'OPD');
        $setda = $this->org('SETDA', 'Sekretariat Daerah', 'OPD');
        $kominfo = $this->org('KOMINFO', 'Dinas Komunikasi dan Informatika', 'OPD');

        $this->applicant = $this->applicantOrg('YAYASAN-CB', 'Yayasan Pendidikan Cahaya Bangsa', 'BADAN_LEMBAGA');

        BudgetCeiling::updateOrCreate(['fiscal_year_id' => $this->fy->id, 'opd_id' => $this->opd->id], [
            'account_code' => '5.1.05.01.01.0001', 'dpa_ref' => 'DPA-DISDIKBUD/2026', 'ceiling' => 5_000_000_000,
        ]);

        $this->u = [
            'admin' => $this->user('admin@ehibah.test', 'Admin Sistem Kominfo', 'ADMIN', $kominfo, 'Administrator'),
            'pemohon' => $this->user('pemohon@ehibah.test', 'Budi Santoso (Ketua Yayasan)', 'PEMOHON', $this->applicant, 'Ketua Yayasan'),
            'opd' => $this->user('opd@ehibah.test', 'Siti Aminah (Staf Verifikator OPD)', 'OPD', $this->opd, 'Kasubag Program'),
            'kepala_opd' => $this->user('kadis@ehibah.test', 'Drs. Ahmad Fauzi (Kepala Dinas)', 'OPD', $this->opd, 'Kepala Dinas Pendidikan dan Kebudayaan'),
            'verifikator' => $this->user('verifikator@ehibah.test', 'Rina Marlina (Tim Verifikasi)', 'VERIFIKATOR', $setda, 'Anggota Tim Verifikasi'),
            'tapd' => $this->user('tapd@ehibah.test', 'Dedi Kurniawan (TAPD)', 'TAPD', $setda, 'Sekretaris TAPD'),
            'bupati' => $this->user('bupati@ehibah.test', 'Bupati Kepahiang', 'BUPATI', $setda, 'Bupati'),
            'bpkad' => $this->user('bpkad@ehibah.test', 'Yeni Safitri (BPKAD/BUD)', 'BPKAD', $bpkad, 'Kabid Perbendaharaan'),
            'inspektorat' => $this->user('inspektorat@ehibah.test', 'Hendra Gunawan (Inspektorat)', 'INSPEKTORAT', $insp, 'Auditor Ahli Muda'),
        ];
    }

    private function org(string $code, string $name, string $type): Organization
    {
        return Organization::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'address' => 'Kepahiang, Bengkulu']);
    }

    public function applicantOrg(string $code, string $name, string $category): Organization
    {
        return Organization::firstOrCreate(['code' => $code], [
            'name' => $name, 'type' => 'LEMBAGA', 'recipient_category_id' => RecipientCategory::where('code', $category)->value('id'),
            'address' => 'Jl. Merdeka No. 1, Kepahiang', 'contact' => '0732-000000', 'npwp' => '01.234.567.8-301.000',
            'legal_doc_no' => 'AHU-0001234.AH.01.04', 'legal_doc_date' => '2019-05-10', 'chair_name' => 'Budi Santoso',
            'secretary_name' => 'Rahma Dewi', 'bank_name' => 'Bank Bengkulu', 'bank_account_no' => '0123456789', 'bank_account_name' => $name,
        ]);
    }

    public function user(string $email, string $name, string $role, Organization $org, string $position): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => self::password(), 'organization_id' => $org->id, 'position' => $position,
            'account_status' => 'ACTIVE', 'is_active' => true, 'password_changed_at' => now(),
        ]);
        $user->roles()->syncWithoutDetaching([Role::where('code', $role)->value('id')]);
        $user->forgetRoleCache();

        return $user;
    }

    /** Pengguna pemohon dari lembaga pemilik usulan (dibuat bila belum ada). */
    public function pemohonOf(GrantApplication $app): User
    {
        $u = User::query()->where('organization_id', $app->applicant_org_id)
            ->whereHas('roles', fn ($q) => $q->where('code', 'PEMOHON'))->orderBy('id')->first();

        return $u ?? $this->user('pemohon.'.strtolower($app->applicant->code).'@ehibah.test', 'Ketua '.$app->applicant->name, 'PEMOHON', $app->applicant, 'Ketua');
    }

    // ---------------------------------------------------------------- usulan
    public function newApplication(array $o = []): GrantApplication
    {
        $type = GrantType::where('code', $o['grant_type'] ?? 'HIBAH_UANG')->firstOrFail();
        unset($o['grant_type']);
        $applicant = $o['applicant'] ?? $this->applicant;
        unset($o['applicant']);
        $amount = $o['amount'] ?? 100_000_000;
        unset($o['amount']);

        $app = GrantApplication::create($o + [
            'fiscal_year_id' => $this->fy->id,
            'applicant_org_id' => $applicant->id,
            'opd_id' => $this->opd->id,
            'grant_type_id' => $type->id,
            'recipient_category_id' => $applicant->recipient_category_id,
            'regulation_id' => Regulation::where('code', 'PERBUP-KPH-25-2023')->value('id'),
            'created_by' => ($this->u['pemohon']->organization_id === $applicant->id ? $this->u['pemohon'] : $this->user('pemohon.'.strtolower($applicant->code).'@ehibah.test', 'Ketua '.$applicant->name, 'PEMOHON', $applicant, 'Ketua'))->id,
            'title' => 'Peningkatan Mutu Pendidikan Dasar Yayasan Cahaya Bangsa',
            'background' => 'Mutu belajar di wilayah binaan masih rendah dan memerlukan sarana pendukung.',
            'purpose' => 'Meningkatkan kualitas pembelajaran bagi 300 siswa kurang mampu.',
            'target' => '300 siswa pendidikan dasar di Kabupaten Kepahiang.',
            'program_activity' => 'Pengadaan perlengkapan belajar dan pelatihan guru.',
            'location' => 'Kecamatan Kepahiang, Kabupaten Kepahiang',
            'activity_start' => '2026-03-01', 'activity_end' => '2026-11-30',
            'committee' => [['nama' => 'Budi Santoso', 'jabatan' => 'Ketua'], ['nama' => 'Rahma Dewi', 'jabatan' => 'Sekretaris'], ['nama' => 'Agus Salim', 'jabatan' => 'Bendahara']],
            'is_recurring' => false, 'funding_declaration' => true,
            'amount_requested' => $amount, 'status' => Status::DRAFT,
        ]);

        $half = intdiv($amount, 2);
        foreach ([['Pengadaan perlengkapan belajar', 1, 'paket', $half], ['Pelatihan guru dan honor narasumber', 1, 'paket', $amount - $half]] as $i => [$d, $q, $un, $p]) {
            $app->items()->create(['description' => $d, 'quantity' => $q, 'unit' => $un, 'unit_price' => $p, 'subtotal' => $p, 'sort_order' => $i]);
        }

        return $app->fresh(['grantType', 'category']);
    }

    /** Unggah seluruh dokumen wajib suatu tahap lewat DocumentService sungguhan. */
    public function uploadAll(GrantApplication $app, string $stage, ?array $related = null, ?User $by = null, bool $verify = false): void
    {
        $by ??= match ($stage) {
            'PENGAJUAN', 'LPJ' => $this->pemohonOf($app), default => $this->u['opd'],
        };
        $docs = app(DocumentService::class);
        foreach ((new DocumentChecklist)->requirementsFor($app, $stage) as $r) {
            $file = File::createWithContent(Str::lower($r->code).'.pdf', "%PDF-1.4\n% E-Hibah contoh dokumen {$r->name}\n%%EOF");
            $doc = $docs->store($app, $by, $stage, $r->code, $file, $related, $r->requires_validity ? now()->addYear()->toDateString() : null);
            if ($verify) {
                $docs->review($doc, $this->u['opd'], 'SESUAI', null);
            }
        }
    }

    public function act(GrantApplication $app, string $action, string $actor, array $data = []): GrantApplication
    {
        $app = $app->fresh();
        $user = $actor === 'pemohon' ? $this->pemohonOf($app) : $this->u[$actor];

        return $this->wf->perform($app, $action, $user, $data);
    }

    private const ORDER = [
        'DRAFT', 'SUBMITTED', 'ADMIN_CHECK', 'TECH_CHECK', 'RECOMMENDED', 'TAPD_REVIEW', 'APPROVED',
        'BUDGETED', 'NPHD_SIGNED', 'DISBURSED', 'LPJ_REVIEW', 'CLOSED',
    ];

    /** Memajukan usulan sampai status target menurut alur normal. */
    public function advance(GrantApplication $app, Status $target): GrantApplication
    {
        $app = $app->fresh();
        while ($app->status !== $target) {
            $i = array_search($app->status->value, self::ORDER, true);
            if ($i === false || $i >= count(self::ORDER) - 1) {
                throw new \LogicException('Tidak dapat memajukan dari '.$app->status->value.' ke '.$target->value);
            }
            $app = $this->step($app, self::ORDER[$i]);
        }

        return $app;
    }

    private function step(GrantApplication $app, string $from): GrantApplication
    {
        $p = app(ProcessService::class);
        switch ($from) {
            case 'DRAFT':
                $this->uploadAll($app, 'PENGAJUAN');

                return $this->act($app, 'submit', 'pemohon');
            case 'SUBMITTED':
                return $this->act($app, 'start_admin', 'opd');
            case 'ADMIN_CHECK':
                $docs = app(DocumentService::class);
                foreach ($app->documents()->where('is_current', true)->where('stage', 'PENGAJUAN')->get() as $d) {
                    $docs->review($d, $this->u['opd'], 'SESUAI', null);
                }

                return $this->act($app, 'admin_pass', 'opd', ['criteria' => array_fill_keys(array_keys(WorkflowService::CRITERIA), true)]);
            case 'TECH_CHECK':
                $p->recordTechnical($app->fresh(), $this->u['verifikator'], ['relevansi' => 85, 'rab' => 80, 'manfaat' => 90, 'kesiapan' => 75], 'Layak didanai.');

                return $this->act($app, 'recommend', 'opd', [
                    'letter_no' => '900/'.rand(100, 999).'/DISDIKBUD/2026', 'letter_date' => now()->toDateString(), 'amount_recommended' => $app->amount_requested,
                ]);
            case 'RECOMMENDED':
                return $this->act($app, 'tapd_start', 'tapd');
            case 'TAPD_REVIEW':
                $p->recordTapdReview($app->fresh(), $this->u['tapd'], (int) $app->amount_recommended, 'Dipertimbangkan sesuai kemampuan keuangan daerah.');
                $decree = Decree::firstOrCreate(['decree_no' => '188.45/KEP/BUP/2026'], [
                    'fiscal_year_id' => $this->fy->id, 'decree_date' => '2026-06-15', 'title' => 'Penerima Hibah Tahun Anggaran 2026',
                    'perda_ref' => 'Perda APBD Kab. Kepahiang No. 9 Tahun 2025', 'perkada_ref' => 'Perbup Penjabaran APBD No. 80 Tahun 2025',
                    'created_by' => $this->u['tapd']->id,
                ]);

                return $this->act($app, 'approve', 'bupati', ['decree_id' => $decree->id, 'amount_approved' => $app->amount_recommended]);
            case 'APPROVED':
                return $this->act($app, 'confirm_budget', 'bpkad', ['account_code' => '5.1.05.01.01.0001', 'dpa_ref' => 'DPA-DISDIKBUD/2026']);
            case 'BUDGETED':
                $p->draftNphd($app->fresh(), $this->u['opd'], [
                    'nphd_no' => '900/NPHD/'.rand(100, 999).'/2026', 'nphd_date' => now()->toDateString(),
                    'signer_user_id' => $this->u['kepala_opd']->id, 'recipient_signer_name' => $app->applicant->chair_name,
                ]);
                $this->uploadAll($app->fresh(), 'NPHD');

                return $this->act($app, 'sign_nphd', 'opd', ['signature_method' => 'BASAH', 'signed_at' => now()->toDateString()]);
            case 'NPHD_SIGNED':
                return $app->isMoney() ? $this->disburseMoney($app, 1) : $this->deliverGoods($app);
            case 'DISBURSED':
                return $this->submitFinalReport($app);
            case 'LPJ_REVIEW':
                return $this->closeOut($app);
        }
        throw new \LogicException("Langkah tidak dikenal: $from");
    }

    public function disburseMoney(GrantApplication $app, int $stage): GrantApplication
    {
        $svc = app(DisbursementService::class);
        $fresh = $app->fresh();
        if ($stage === 1 || $fresh->disbursements()->count() === 0) {
            $this->uploadAll($fresh, 'PENCAIRAN');
        }
        $d = $svc->request($fresh, $this->u['opd'], $stage, (string) Str::uuid(), 'SPP/'.rand(100, 999), now()->toDateString());
        $svc->process($d, $this->u['bpkad'], [
            'spm_no' => 'SPM/'.rand(1000, 9999), 'sp2d_no' => 'SP2D-LS/'.rand(1000, 9999), 'sp2d_date' => now()->toDateString(),
            'rkud_ref' => 'RKUD/'.rand(10000, 99999), 'bank_account_ref' => $fresh->applicant->bank_account_no,
        ]);

        return $app->fresh();
    }

    private function deliverGoods(GrantApplication $app): GrantApplication
    {
        $g = app(GoodsService::class);
        $app = $app->fresh();
        $g->save($app, $this->u['opd'], [
            'procurement_ref' => 'Kontrak 027/PBJ/2026', 'procurement_date' => now()->subDays(10)->toDateString(),
            'bast_no' => 'BAST/'.rand(100, 999).'/2026', 'bast_date' => now()->toDateString(), 'received_by_name' => $app->applicant->chair_name,
        ], [['description' => 'Laptop pembelajaran', 'spec' => 'Core i3, 8GB', 'quantity' => 10, 'unit' => 'unit', 'unit_value' => intdiv((int) $app->amount_approved, 10)]]);
        $this->uploadAll($app, 'PENYERAHAN');
        $g->markDelivered($app->fresh(), $this->u['opd']);
        $g->recordAsset($app->fresh(), $this->u['bpkad'], 'KIB-B/2026/'.rand(100, 999));

        return $app->fresh();
    }

    private function submitFinalReport(GrantApplication $app): GrantApplication
    {
        $app = $app->fresh();
        $acc = app(AccountabilityService::class);
        $pem = $this->pemohonOf($app);

        if ($app->isMoney() && $app->disbursement_scheme === 'STAGED') {
            // tahap I: laporan -> diperiksa -> tahap II
            if ($app->disbursements()->where('stage', 2)->doesntExist()) {
                $r1 = $acc->saveDraft($app, $pem, 'TAHAP_1', ['summary' => 'Penggunaan dana tahap I.'], [
                    ['spent_on' => now()->subDays(5)->toDateString(), 'description' => 'Belanja perlengkapan belajar', 'amount' => (int) round($app->disbursedTotal() * 0.8), 'receipt_ref' => 'KW-001'],
                ]);
                $this->uploadAll($app, 'LPJ', ['report', $r1->id]);
                $acc->submitInterim($r1->fresh(), $pem);
                $acc->reviewInterim($r1->fresh(), $this->u['opd'], 'DITERIMA', null);
                $app = $this->disburseMoney($app, 2);
            }
        }

        $acc->setActivityCompleted($app, $pem, now()->subDay()->toDateString());
        $app = $app->fresh();
        $realized = $app->isMoney() ? (int) round($app->disbursedTotal() * 0.95) : 0; // sisa 5% dikembalikan
        $r = $acc->saveDraft($app, $pem, 'AKHIR', [
            'summary' => 'Kegiatan terlaksana sesuai rencana.', 'activity_result' => '300 siswa menerima perlengkapan dan pelatihan.',
        ], $app->isMoney() ? [
            ['spent_on' => now()->subDays(3)->toDateString(), 'description' => 'Realisasi belanja kegiatan', 'amount' => $realized, 'receipt_ref' => 'KW-100'],
        ] : []);
        $this->uploadAll($app, 'LPJ', ['report', $r->id]);

        return $this->act($app, 'submit_lpj', 'pemohon');
    }

    private function closeOut(GrantApplication $app): GrantApplication
    {
        $app = $app->fresh();
        $report = $app->reports()->where('report_type', 'AKHIR')->latest('id')->first();
        $docs = app(DocumentService::class);
        $acc = app(AccountabilityService::class);

        if ($app->isMoney()) {
            $remaining = $app->disbursedTotal() - $report->total_realization;
            if ($remaining > 0) {
                $acc->recordReturn($app, $this->pemohonOf($app), ['amount' => $remaining, 'returned_on' => now()->toDateString(), 'kas_ref' => 'STS/'.rand(1000, 9999)]);
                $r = (new DocumentChecklist)->requirementsFor($app, 'LPJ')->firstWhere('code', 'BUKTI_SETOR');
                $docs->store($app, $this->pemohonOf($app), 'LPJ', $r->code,
                    File::createWithContent('bukti-setor.pdf', "%PDF-1.4\n%%EOF"), ['report', $report->id]);
            }
        }
        foreach ($app->documents()->where('is_current', true)->where('stage', 'LPJ')->get() as $d) {
            $docs->review($d, $this->u['opd'], 'SESUAI', null);
        }
        $acc->recordMonitoring($app, $this->u['opd'], [
            'type' => 'MONEV', 'visited_at' => now()->toDateString(), 'findings' => 'Kegiatan sesuai proposal dan terdokumentasi baik.',
            'recommendation' => 'Dipertahankan.', 'follow_up_status' => 'SELESAI',
        ]);

        return $this->act($app, 'close', 'opd');
    }

    /** Dokumen sebagai Eloquent untuk uji akses. */
    public function firstDoc(GrantApplication $app): ?ApplicationDocument
    {
        return $app->documents()->first();
    }
}
