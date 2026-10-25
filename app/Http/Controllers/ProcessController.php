<?php

namespace App\Http\Controllers;

use App\Models\AccountabilityReport;
use App\Models\Disbursement;
use App\Models\GrantApplication;
use App\Services\AccountabilityService;
use App\Services\DisbursementService;
use App\Services\GoodsService;
use App\Services\ProcessService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

/** Tindakan proses per usulan. Validasi bisnis & kewenangan berada di layanan (server-side). */
class ProcessController extends Controller
{
    public function __construct(
        private WorkflowService $wf,
        private ProcessService $process,
        private DisbursementService $disb,
        private GoodsService $goods,
        private AccountabilityService $acc,
    ) {
    }

    public function transition(Request $request, GrantApplication $application, string $action)
    {
        $this->authorizeView($application);
        abort_unless(isset(WorkflowService::TRANSITIONS[$action]) && (WorkflowService::TRANSITIONS[$action]['ui'] ?? true), 404);

        $app = $this->wf->perform($application, $action, $request->user(), $request->only([
            'note', 'lock_version', 'criteria', 'letter_no', 'letter_date', 'amount_recommended', 'decree_id', 'amount_approved',
            'account_code', 'dpa_ref', 'signature_method', 'signed_at', 'signature_ref',
        ]));

        return redirect()->route('applications.show', $app)->with('success', 'Berhasil: '.WorkflowService::TRANSITIONS[$action]['label'].'. Status sekarang "'.$app->status->label().'".');
    }

    public function technical(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $v = $this->process->recordTechnical($application, $request->user(), (array) $request->input('scores', []), $request->input('notes'));

        return back()->with('success', 'Penilaian teknis tersimpan: skor '.$v->score.' ('.($v->result === 'LULUS' ? 'layak' : 'tidak layak').').');
    }

    public function tapd(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $request->validate(['amount' => 'required|integer|min:1']);
        $this->process->recordTapdReview($application, $request->user(), (int) $request->input('amount'), $request->input('notes'));

        return back()->with('success', 'Pertimbangan TAPD dan alokasi pagu tersimpan.');
    }

    public function nphd(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $n = $this->process->draftNphd($application, $request->user(), $request->all());

        return back()->with('success', "Draf NPHD {$n->nphd_no} (versi {$n->version}) disusun. Cetak, tandatangani, lalu unggah hasil tanda tangan.");
    }

    // ---- penyaluran hibah uang
    public function disbursementRequest(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $request->validate(['stage' => 'required|integer|in:1,2', 'idempotency_key' => 'required|string|max:80', 'spp_no' => 'nullable|string|max:60', 'spp_date' => 'nullable|date']);
        $this->disb->request($application, $request->user(), (int) $request->input('stage'), $request->input('idempotency_key'),
            $request->input('spp_no'), $request->input('spp_date'), $request->input('notes'));

        return back()->with('success', 'Pengajuan penyaluran (SPP) dikirim ke BPKAD.');
    }

    public function disbursementProcess(Request $request, Disbursement $disbursement)
    {
        $this->authorizeView($disbursement->application);
        $this->disb->process($disbursement, $request->user(), $request->all());

        return back()->with('success', 'Penyaluran dicatat: SP2D-LS terbit dan transfer RKUD tercatat.');
    }

    public function disbursementReject(Request $request, Disbursement $disbursement)
    {
        $this->authorizeView($disbursement->application);
        $this->disb->reject($disbursement, $request->user(), (string) $request->input('reason'));

        return back()->with('success', 'Pengajuan penyaluran ditolak.');
    }

    public function exceptionRequest(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $this->disb->requestFullException($application, $request->user(), (string) $request->input('basis'), (string) $request->input('reason'));

        return back()->with('success', 'Pengecualian pencairan 100% diajukan. Menunggu otorisasi BPKAD.');
    }

    public function exceptionDecide(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $this->disb->authorizeFullException($application, $request->user(), $request->input('decision') === 'approve', $request->input('note'));

        return back()->with('success', 'Keputusan pengecualian tercatat.');
    }

    // ---- hibah barang
    public function goodsSave(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $this->goods->save($application, $request->user(), $request->all(), (array) $request->input('items', []));

        return back()->with('success', 'Data penyerahan barang tersimpan. Unggah BAST & pakta integritas, lalu nyatakan diserahkan.');
    }

    public function goodsDeliver(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $this->goods->markDelivered($application, $request->user());

        return back()->with('success', 'Penyerahan barang dinyatakan selesai. Menunggu pencatatan aset oleh BPKAD.');
    }

    public function goodsAsset(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $this->goods->recordAsset($application, $request->user(), (string) $request->input('asset_ref'));

        return back()->with('success', 'Barang tercatat keluar dari aset daerah. Status: Disalurkan.');
    }

    // ---- LPJ
    public function reportSave(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $r = $this->acc->saveDraft($application, $request->user(), (string) $request->input('report_type', 'AKHIR'),
            $request->only(['summary', 'activity_result', 'period_start', 'period_end', 'bpk_audit_date']), (array) $request->input('expenses', []));

        return back()->with('success', 'Draf laporan tersimpan. Total realisasi: Rp '.number_format($r->total_realization, 0, ',', '.'));
    }

    public function activityDone(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $request->validate(['activity_completed_at' => 'required|date']);
        $a = $this->acc->setActivityCompleted($application, $request->user(), $request->input('activity_completed_at'));

        return back()->with('success', 'Tenggat LPJ dihitung: '.\App\Support\Fmt::date($a->lpj_due_date).'.');
    }

    public function reportSubmit(Request $request, AccountabilityReport $report)
    {
        $this->authorizeView($report->application);
        $this->acc->submitInterim($report, $request->user());

        return back()->with('success', 'Laporan tahap I diajukan untuk diperiksa OPD.');
    }

    public function reportReview(Request $request, AccountabilityReport $report)
    {
        $this->authorizeView($report->application);
        $this->acc->reviewInterim($report, $request->user(), (string) $request->input('result'), $request->input('notes'));

        return back()->with('success', 'Hasil pemeriksaan laporan tercatat.');
    }

    public function fundReturn(Request $request, GrantApplication $application)
    {
        $this->authorizeView($application);
        $this->acc->recordReturn($application, $request->user(), $request->all());

        return back()->with('success', 'Setoran sisa dana tercatat. Unggah bukti setor pada dokumen LPJ.');
    }
}
