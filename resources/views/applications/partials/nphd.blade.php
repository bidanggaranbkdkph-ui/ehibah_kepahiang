@php use App\Enums\Status; $n = $app->activeNphd(); @endphp
<div class="stack">
    <div class="card {{ $n && $n->status === 'DITANDATANGANI' ? 'tone-green' : '' }}">
        <div class="card-h"><h3>Naskah Perjanjian Hibah Daerah (NPHD)</h3><span class="muted small">SOP 8 · Pasal 8 · Lampiran {{ $app->isMoney() ? 'B.1' : 'B.2' }}</span></div>
        @if ($n)
            <dl class="def">
                <dt>Nomor</dt><dd><b>{{ $n->nphd_no }}</b> <span class="badge {{ $n->status === 'DITANDATANGANI' ? 'green' : 'blue' }}">{{ $n->status === 'DITANDATANGANI' ? 'Ditandatangani' : 'Draf' }}</span> <span class="badge gray">v{{ $n->version }}</span></dd>
                <dt>Tanggal</dt><dd>{{ tgl($n->nphd_date) }}</dd><dt>Nilai</dt><dd><b>{{ rupiah($n->amount) }}</b> <span class="muted small">({{ ucfirst(\App\Support\Fmt::terbilang($n->amount)) }} rupiah)</span></dd>
                <dt>Pihak pertama</dt><dd>{{ $n->signer_name }} — {{ $n->signer_position }}</dd><dt>Pihak kedua</dt><dd>{{ $n->recipient_signer_name }} — {{ $app->applicant->name }}</dd>
                @if ($n->signed_at)<dt>Ditandatangani</dt><dd>{{ tgl($n->signed_at) }} ({{ $n->signature_method === 'TTE' ? 'TTE'.($n->signature_ref ? ' · '.$n->signature_ref : '') : 'tanda tangan basah' }})</dd>@endif
            </dl>
            <div class="row mt"><a class="btn" target="_blank" href="{{ route('print', [$app, 'nphd']) }}"><svg class="i"><use href="#i-print"/></svg>Cetak NPHD (PDF)</a>
                <a class="btn" target="_blank" href="{{ route('print', [$app, 'pakta']) }}"><svg class="i"><use href="#i-print"/></svg>Pakta integritas ({{ $app->isMoney() ? 'C.1' : 'C.2' }})</a></div>
        @else <div class="empty small">NPHD belum disusun.</div> @endif

        @if ($s === Status::BUDGETED && $isOpd)
            <details class="inline" @if (! $n) open @endif style="margin-top:.8rem"><summary><svg class="i"><use href="#i-edit"/></svg>{{ $n ? 'Susun ulang NPHD (versi baru)' : 'Susun NPHD' }}</summary>
                <div class="body"><form method="POST" action="{{ route('process.nphd', $app) }}" class="stack">@csrf
                    <div class="form-grid">
                        <div><label>Nomor NPHD <span class="req">*</span></label><input type="text" name="nphd_no" value="{{ old('nphd_no', $n?->nphd_no) }}" required></div>
                        <div><label>Tanggal NPHD <span class="req">*</span></label><input type="date" name="nphd_date" value="{{ old('nphd_date', now()->toDateString()) }}" required></div>
                        <div><label>Penandatangan — Kepala SKPD <span class="req">*</span></label><select name="signer_user_id" required>@forelse ($signers as $sg)<option value="{{ $sg->id }}" @selected(old('signer_user_id', $n?->signer_user_id) == $sg->id)>{{ $sg->name }} — {{ $sg->position }}</option>@empty<option value="">Tidak ada pejabat berjabatan "Kepala…" pada OPD ini</option>@endforelse</select>
                            <div class="hint">Pasal 8: NPHD ditandatangani Kepala SKPD sesuai urusan dan kewenangan.</div></div>
                        <div><label>Penandatangan penerima <span class="req">*</span></label><input type="text" name="recipient_signer_name" value="{{ old('recipient_signer_name', $n?->recipient_signer_name ?? $app->applicant->chair_name) }}" required></div>
                    </div>
                    <div class="hint">Nilai NPHD otomatis sama dengan nilai penetapan ({{ rupiah($app->amount_approved) }}).</div>
                    <button class="btn primary">Simpan draf NPHD</button></form></div></details>
        @endif
    </div>
    @if (in_array($s, [Status::BUDGETED], true))
        <div class="alert info"><svg class="i"><use href="#i-info"/></svg><div>Alur: (1) susun NPHD → (2) cetak & tandatangani kedua pihak → (3) unggah <b>NPHD bertanda tangan</b> di tab Dokumen → (4) OPD menekan <b>Konfirmasi NPHD ditandatangani</b>. Penyaluran baru dapat diajukan setelah itu.</div></div>
    @endif
</div>
