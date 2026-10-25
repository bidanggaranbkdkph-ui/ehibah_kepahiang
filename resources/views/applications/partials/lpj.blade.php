@php
    use App\Enums\Status;
    $user = auth()->user();
    $draftable = $isApplicant && $s === Status::DISBURSED;
    $statusBadge = ['DRAFT' => ['Draf', 'gray'], 'DIAJUKAN' => ['Diajukan', 'blue'], 'PERLU_PERBAIKAN' => ['Perlu perbaikan', 'amber'], 'DITERIMA' => ['Diterima', 'green']];
    $final = $app->reports->where('report_type', 'AKHIR')->last();
    $interim = $app->reports->where('report_type', 'TAHAP_1')->last();
    $remaining = ($app->isMoney() && $final) ? max(0, $app->disbursedTotal() - $final->total_realization) : 0;
@endphp
<div class="stack">
    {{-- Tenggat --}}
    <div class="card {{ $app->lpj_overdue ? 'tone-red' : 'tone-navy' }}">
        <div class="card-h"><h3>Tenggat LPJ</h3><span class="muted small">Pasal 19 · 1 bulan setelah kegiatan selesai, paling lambat 10 Januari tahun berikutnya</span></div>
        <dl class="def"><dt>Rencana selesai kegiatan</dt><dd>{{ tgl($app->activity_end) }}</dd><dt>Selesai aktual</dt><dd>{{ tgl($app->activity_completed_at) }}</dd><dt>Tenggat LPJ</dt><dd><b>{{ tgl($app->lpj_due_date) }}</b> @if ($app->lpj_overdue)<span class="badge red">terlambat {{ $daysLate }} hari</span>@endif</dd>
            <dt>Surat peringatan</dt><dd>{{ $app->warnings->count() }} @if ($app->warnings->count() && $user->hasRole('OPD', 'PEMOHON', 'INSPEKTORAT'))· <a target="_blank" href="{{ route('print', [$app, 'peringatan']) }}">cetak</a>@endif</dd></dl>
        @if ($draftable)
            <form method="POST" action="{{ route('process.report.done', $app) }}" class="row mt"><label class="sr">Tanggal selesai</label>@csrf
                <input type="date" name="activity_completed_at" value="{{ $app->activity_completed_at?->toDateString() }}" max="{{ now()->toDateString() }}" required style="width:auto"><button class="btn sm navy">Hitung tenggat dari tanggal selesai</button></form>
        @endif
    </div>

    {{-- Laporan tahap I --}}
    @if ($app->isMoney() && $app->disbursement_scheme === 'STAGED')
        <div class="card">
            <div class="card-h"><h3>Laporan penggunaan tahap I</h3>@if ($interim)<span class="badge {{ $statusBadge[$interim->review_status][1] }}">{{ $statusBadge[$interim->review_status][0] }}</span>@endif<span class="sp"></span><span class="muted small">syarat penyaluran tahap II</span></div>
            @if ($interim) @include('applications.partials.report-body', ['report' => $interim, 'editable' => $draftable && in_array($interim->review_status, ['DRAFT', 'PERLU_PERBAIKAN'])])
            @elseif ($draftable && $app->disbursedTotal() > 0 && $app->disbursements->where('stage', 2)->where('status', 'DISALURKAN')->isEmpty()) @include('applications.partials.report-form', ['type' => 'TAHAP_1', 'report' => null])
            @else <div class="empty small">Belum ada laporan tahap I.</div> @endif
        </div>
    @endif

    {{-- LPJ akhir --}}
    <div class="card">
        <div class="card-h"><h3>LPJ akhir</h3>@if ($final)<span class="badge {{ $statusBadge[$final->review_status][1] }}">{{ $statusBadge[$final->review_status][0] }}</span>@if ($final->is_late)<span class="badge red">terlambat</span>@endif @endif<span class="sp"></span><span class="muted small">Pasal 16 · Lampiran E</span></div>
        @if ($final) @include('applications.partials.report-body', ['report' => $final, 'editable' => $draftable && in_array($final->review_status, ['DRAFT', 'PERLU_PERBAIKAN'])])
        @elseif ($draftable) @include('applications.partials.report-form', ['type' => 'AKHIR', 'report' => null])
        @else <div class="empty small">LPJ akhir belum disusun pemohon.</div> @endif
        @if ($final && $final->review_notes && $final->review_status === 'PERLU_PERBAIKAN')<div class="alert warning mt"><svg class="i"><use href="#i-alert"/></svg><div><b>Perlu perbaikan:</b> {{ $final->review_notes }}</div></div>@endif
    </div>

    {{-- Sisa dana --}}
    @if ($app->isMoney() && $final)
        <div class="card {{ $remaining > $app->returnedTotal() ? 'tone-amber' : '' }}">
            <div class="card-h"><h3>Sisa dana & pengembalian ke kas daerah</h3><span class="muted small">Pasal 20</span></div>
            <dl class="def"><dt>Dana tersalur</dt><dd>{{ rupiah($app->disbursedTotal()) }}</dd><dt>Realisasi (LPJ akhir)</dt><dd>{{ rupiah($final->total_realization) }}</dd><dt>Sisa dana</dt><dd><b>{{ rupiah($remaining) }}</b></dd><dt>Sudah disetor</dt><dd>{{ rupiah($app->returnedTotal()) }}</dd></dl>
            @if ($app->fundReturns->count())<div class="table-wrap mt"><table class="table-plain"><thead><tr><th>Tanggal</th><th class="num">Nilai</th><th>Ref. kas daerah</th><th>Pemberitahuan SKPD/BUD</th></tr></thead><tbody>@foreach ($app->fundReturns as $fr)<tr><td>{{ tgl($fr->returned_on) }}</td><td class="num">{{ rupiah($fr->amount) }}</td><td>{{ $fr->kas_ref }}</td><td>{{ tgljam($fr->notified_at) }}</td></tr>@endforeach</tbody></table></div>@endif
            @if ($remaining > $app->returnedTotal() && ($isApplicant || $isOpd))
                <details class="inline" open style="margin-top:.7rem"><summary><svg class="i"><use href="#i-wallet"/></svg>Catat setoran sisa dana</summary><div class="body">
                    <form method="POST" action="{{ route('process.return', $app) }}" class="stack">@csrf
                        <div class="form-grid"><div><label>Nilai setoran (Rp) <span class="req">*</span></label><input type="number" name="amount" min="1" max="{{ $remaining - $app->returnedTotal() }}" value="{{ $remaining - $app->returnedTotal() }}" required></div>
                            <div><label>Tanggal setor <span class="req">*</span></label><input type="date" name="returned_on" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
                            <div class="full"><label>Ref. kas daerah / STS <span class="req">*</span></label><input type="text" name="kas_ref" required></div></div>
                        <button class="btn primary">Catat setoran</button><div class="hint">Unggah bukti setor pada dokumen LPJ ("Bukti setor sisa dana"). SKPD dan BUD diberi notifikasi.</div></form></div></details>
            @endif
        </div>
    @endif

    {{-- Monev --}}
    <div class="card">
        <div class="card-h"><h3>Monitoring & evaluasi</h3><span class="sp"></span><span class="muted small">Pasal 21 · hasil ke Bupati, tembusan Inspektorat</span></div>
        @forelse ($app->monitoring as $m)
            <div class="doc-item" style="grid-template-columns:1fr auto"><div><b>{{ $m->type === 'MONEV' ? 'Monitoring' : 'Evaluasi' }} · {{ tgl($m->visited_at) }} · {{ $m->officer->name }}</b>
                <div class="small">{{ $m->findings }}</div>@if ($m->recommendation)<div class="small muted">Rekomendasi: {{ $m->recommendation }}</div>@endif @if ($m->follow_up)<div class="small muted">Tindak lanjut: {{ $m->follow_up }}</div>@endif
                <div class="small muted">Dikirim ke Bupati {{ tgl($m->sent_to_bupati_at, true) }} · tembusan Inspektorat {{ tgl($m->sent_to_inspektorat_at, true) }}</div></div>
                <div><span class="badge {{ $m->follow_up_status === 'OPEN' ? 'amber' : 'green' }}">TL {{ $m->follow_up_status === 'OPEN' ? 'terbuka' : 'selesai' }}</span>
                    @if ($m->follow_up_status === 'OPEN' && $perm('monev', 'P'))<form method="POST" action="{{ route('monev.followup', $m) }}" style="margin-top:.3rem">@csrf<button class="btn sm">Tandai selesai</button></form>@endif</div></div>
        @empty <div class="empty small">Belum ada catatan monitoring.</div> @endforelse
        @if ($perm('monev', 'P') && ($isOpd || $user->hasRole('INSPEKTORAT')) && in_array($s, [Status::DISBURSED, Status::LPJ_REVIEW, Status::CLOSED], true))
            <details class="inline" style="margin-top:.7rem"><summary><svg class="i"><use href="#i-plus"/></svg>Tambah catatan monitoring</summary><div class="body">
                <form method="POST" action="{{ route('monev.store', $app) }}" class="stack">@csrf
                    <div class="form-grid"><div><label>Jenis</label><select name="type"><option value="MONEV">Monitoring</option><option value="EVALUASI">Evaluasi</option></select></div>
                        <div><label>Tanggal <span class="req">*</span></label><input type="date" name="visited_at" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
                        <div class="full"><label>Temuan <span class="req">*</span></label><textarea name="findings" rows="3" minlength="10" required></textarea></div>
                        <div class="full"><label>Rekomendasi</label><textarea name="recommendation" rows="2"></textarea></div>
                        <div class="full"><label>Tindak lanjut yang diminta</label><textarea name="follow_up" rows="2"></textarea></div>
                        <div><label>Batas tindak lanjut</label><input type="date" name="follow_up_due"></div></div>
                    <button class="btn primary">Simpan & sampaikan</button></form></div></details>
        @endif
    </div>
</div>
