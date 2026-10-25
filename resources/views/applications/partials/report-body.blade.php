@php $isMoney = $app->isMoney(); $user = auth()->user(); @endphp
@if ($editable)
    @include('applications.partials.report-form', ['type' => $report->report_type, 'report' => $report])
@else
    <dl class="def"><dt>Uraian kegiatan</dt><dd>{!! nl2br(e($report->summary)) !!}</dd>@if ($report->activity_result)<dt>Hasil</dt><dd>{!! nl2br(e($report->activity_result)) !!}</dd>@endif
        @if ($report->bpk_audit_date)<dt>Pemeriksaan BPK</dt><dd>{{ tgl($report->bpk_audit_date) }}</dd>@endif
        <dt>Diajukan</dt><dd>{{ tgljam($report->submitted_at) }}</dd></dl>
    @if ($isMoney && $report->expenses->count())
        <div class="table-wrap mt"><table class="table-plain"><thead><tr><th>Tanggal</th><th>Uraian</th><th>Ref. bukti</th><th class="num">Nilai</th></tr></thead><tbody>
            @foreach ($report->expenses as $e)<tr><td>{{ tgl($e->spent_on, true) }}</td><td>{{ $e->description }}</td><td>{{ $e->receipt_ref }}</td><td class="num">{{ rupiah($e->amount, false) }}</td></tr>@endforeach</tbody>
            <tfoot><tr><td colspan="3">Total realisasi</td><td class="num">{{ rupiah($report->total_realization) }}</td></tr></tfoot></table></div>
    @endif
    @if ($report->report_type === 'TAHAP_1' && $report->review_status === 'DIAJUKAN' && $isOpd)
        <form method="POST" action="{{ route('process.report.review', $report) }}" class="stack mt" data-multi>@csrf
            <div><label>Catatan (wajib bila perlu perbaikan)</label><input type="text" name="notes"></div>
            <div class="row"><button class="btn primary" name="result" value="DITERIMA">Terima laporan</button><button class="btn warning" name="result" value="PERLU_PERBAIKAN">Perlu perbaikan</button></div></form>
    @endif
@endif
@if ($report->report_type === 'TAHAP_1' && $report->review_status === 'DRAFT' && $isApplicant)
    <form method="POST" action="{{ route('process.report.submit', $report) }}" class="mt" data-confirm="Ajukan laporan tahap I untuk diperiksa OPD?">@csrf<button class="btn navy">Ajukan laporan tahap I</button><div class="hint">Unggah dokumen laporan di tab Dokumen (bagian "Laporan tahap I") sebelum mengajukan.</div></form>
@endif
