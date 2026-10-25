@php
    $isMoney = $app->isMoney();
    $rows = old('expenses', $report ? $report->expenses->map(fn ($e) => ['spent_on' => $e->spent_on->toDateString(), 'description' => $e->description, 'amount' => $e->amount, 'receipt_ref' => $e->receipt_ref])->all() : []);
@endphp
<form method="POST" action="{{ route('process.report.save', $app) }}" class="stack">@csrf
    <input type="hidden" name="report_type" value="{{ $type }}">
    <div class="form-grid">
        <div class="full"><label>Uraian pelaksanaan kegiatan <span class="req">*</span></label><textarea name="summary" rows="3">{{ old('summary', $report?->summary) }}</textarea></div>
        @if ($type === 'AKHIR')<div class="full"><label>Hasil yang dicapai <span class="req">*</span></label><textarea name="activity_result" rows="2">{{ old('activity_result', $report?->activity_result) }}</textarea></div>@endif
        <div><label>Periode mulai</label><input type="date" name="period_start" value="{{ old('period_start', $report?->period_start?->toDateString() ?? $app->activity_start?->toDateString()) }}"></div>
        <div><label>Periode selesai</label><input type="date" name="period_end" value="{{ old('period_end', $report?->period_end?->toDateString()) }}"></div>
        @if ($app->isParpol() && $type === 'AKHIR')<div class="full"><label>Tanggal pemeriksaan BPK <span class="req">*</span></label><input type="date" name="bpk_audit_date" value="{{ old('bpk_audit_date', $report?->bpk_audit_date?->toDateString()) }}"><div class="hint">Pasal 18: LPJ partai politik disampaikan setelah diperiksa BPK.</div></div>@endif
    </div>
    @if ($isMoney)
        <div data-repeat data-min="0">
            <h4>Rincian realisasi penggunaan dana</h4>
            <div data-list>
                @foreach ($rows as $i => $e)
                    <div class="repeat-row e"><div><label>Tanggal</label><input type="date" name="expenses[{{ $i }}][spent_on]" value="{{ $e['spent_on'] }}" required></div><div class="wide"><label>Uraian</label><input type="text" name="expenses[{{ $i }}][description]" value="{{ $e['description'] }}" required></div><div><label>Ref. bukti</label><input type="text" name="expenses[{{ $i }}][receipt_ref]" value="{{ $e['receipt_ref'] }}"></div><div><label>Nilai (Rp)</label><input type="number" min="1" name="expenses[{{ $i }}][amount]" value="{{ $e['amount'] }}" data-amt required></div><button type="button" class="btn sm danger" data-remove><svg class="i"><use href="#i-x"/></svg></button></div>
                @endforeach
            </div>
            <template><div class="repeat-row e"><div><label>Tanggal</label><input type="date" name="expenses[0][spent_on]" required></div><div class="wide"><label>Uraian</label><input type="text" name="expenses[0][description]" required></div><div><label>Ref. bukti</label><input type="text" name="expenses[0][receipt_ref]"></div><div><label>Nilai (Rp)</label><input type="number" min="1" name="expenses[0][amount]" data-amt required></div><button type="button" class="btn sm danger" data-remove><svg class="i"><use href="#i-x"/></svg></button></div></template>
            <button type="button" class="btn sm" data-add><svg class="i"><use href="#i-plus"/></svg>Tambah baris realisasi</button>
            <div class="total-bar mt"><span>Total realisasi (dihitung otomatis; sisa = dana tersalur − realisasi)</span><b data-total>Rp 0</b></div>
        </div>
    @endif
    <button class="btn primary">Simpan draf laporan</button>
    <div class="hint">Setelah disimpan, unggah dokumen LPJ pada tab Dokumen, lalu {{ $type === 'AKHIR' ? 'ajukan LPJ akhir melalui panel aksi' : 'ajukan laporan tahap I' }}.</div>
</form>
