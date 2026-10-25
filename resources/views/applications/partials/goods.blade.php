@php use App\Enums\Status; $user = auth()->user(); $g = $app->goodsDelivery; @endphp
<div class="stack">
    <div class="alert info"><svg class="i"><use href="#i-info"/></svg><div>Hibah barang <b>tidak melalui SP2D</b>. Bukti penyaluran adalah <b>BAST dan daftar barang</b>; barang wajib dicatat keluar dari aset daerah (Perbup Pasal 13–14, 17).</div></div>
    <div class="card {{ $g?->status === 'DICATAT' ? 'tone-green' : '' }}">
        <div class="card-h"><h3>Pengadaan & penyerahan barang</h3>@if ($g)<span class="sp"></span><span class="badge {{ ['DRAFT' => 'gray', 'DISERAHKAN' => 'blue', 'DICATAT' => 'green'][$g->status] }}">{{ ['DRAFT' => 'Draf', 'DISERAHKAN' => 'Diserahkan', 'DICATAT' => 'Tercatat sebagai aset keluar'][$g->status] }}</span>@endif</div>
        @if ($g)
            <dl class="def"><dt>Dokumen pengadaan</dt><dd>{{ $g->procurement_ref }} · {{ tgl($g->procurement_date) }}</dd><dt>BAST</dt><dd>{{ $g->bast_no }} · {{ tgl($g->bast_date) }}</dd><dt>Penerima barang</dt><dd>{{ $g->received_by_name }}</dd>
                @if ($g->asset_ref)<dt>Referensi aset</dt><dd>{{ $g->asset_ref }} · {{ tgljam($g->asset_recorded_at) }}</dd>@endif</dl>
            <div class="table-wrap mt"><table class="table-plain"><thead><tr><th>Barang</th><th>Spesifikasi</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Nilai satuan</th></tr></thead><tbody>
                @foreach ($g->items as $it)<tr><td>{{ $it->description }}</td><td>{{ $it->spec }}</td><td class="num">{{ rtrim(rtrim(number_format($it->quantity, 2, ',', '.'), '0'), ',') }}</td><td>{{ $it->unit }}</td><td class="num">{{ rupiah($it->unit_value, false) }}</td></tr>@endforeach</tbody></table></div>
        @else <div class="empty small">Data penyerahan belum disusun OPD.</div> @endif

        @if ($s === Status::NPHD_SIGNED && $isOpd && (! $g || $g->status !== 'DICATAT'))
            <details class="inline" @if (! $g) open @endif style="margin-top:.8rem"><summary><svg class="i"><use href="#i-edit"/></svg>{{ $g ? 'Ubah' : 'Susun' }} data penyerahan</summary><div class="body">
                <form method="POST" action="{{ route('process.goods.save', $app) }}" class="stack">@csrf
                    <div class="form-grid">
                        <div><label>Referensi dokumen pengadaan <span class="req">*</span></label><input type="text" name="procurement_ref" value="{{ old('procurement_ref', $g?->procurement_ref) }}" required></div>
                        <div><label>Tanggal pengadaan <span class="req">*</span></label><input type="date" name="procurement_date" value="{{ old('procurement_date', $g?->procurement_date?->toDateString()) }}" required></div>
                        <div><label>No. BAST <span class="req">*</span></label><input type="text" name="bast_no" value="{{ old('bast_no', $g?->bast_no) }}" required></div>
                        <div><label>Tanggal BAST <span class="req">*</span></label><input type="date" name="bast_date" value="{{ old('bast_date', $g?->bast_date?->toDateString()) }}" required></div>
                        <div class="full"><label>Penerima barang <span class="req">*</span></label><input type="text" name="received_by_name" value="{{ old('received_by_name', $g?->received_by_name ?? $app->applicant->chair_name) }}" required></div>
                    </div>
                    @php $gi = old('items', $g ? $g->items->map(fn ($i) => ['description' => $i->description, 'spec' => $i->spec, 'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'unit_value' => $i->unit_value])->all() : []); @endphp
                    <div data-repeat data-min="1"><h4>Daftar barang diserahkan</h4><div data-list>
                        @foreach ($gi as $i => $it)<div class="repeat-row"><div class="wide"><label>Barang</label><input type="text" name="items[{{ $i }}][description]" value="{{ $it['description'] }}" required></div><div><label>Spesifikasi</label><input type="text" name="items[{{ $i }}][spec]" value="{{ $it['spec'] }}"></div><div><label>Jumlah</label><input type="number" step="0.01" name="items[{{ $i }}][quantity]" value="{{ $it['quantity'] }}" required></div><div><label>Satuan</label><input type="text" name="items[{{ $i }}][unit]" value="{{ $it['unit'] }}" required></div><div><label>Nilai satuan</label><input type="number" name="items[{{ $i }}][unit_value]" value="{{ $it['unit_value'] }}"></div><button type="button" class="btn sm danger" data-remove><svg class="i"><use href="#i-x"/></svg></button></div>@endforeach</div>
                        <template><div class="repeat-row"><div class="wide"><label>Barang</label><input type="text" name="items[0][description]" required></div><div><label>Spesifikasi</label><input type="text" name="items[0][spec]"></div><div><label>Jumlah</label><input type="number" step="0.01" name="items[0][quantity]" value="1" required></div><div><label>Satuan</label><input type="text" name="items[0][unit]" value="unit" required></div><div><label>Nilai satuan</label><input type="number" name="items[0][unit_value]"></div><button type="button" class="btn sm danger" data-remove><svg class="i"><use href="#i-x"/></svg></button></div></template>
                        <button type="button" class="btn sm" data-add><svg class="i"><use href="#i-plus"/></svg>Tambah barang</button></div>
                    <button class="btn primary">Simpan</button></form></div></details>
            @if ($g && $g->status === 'DRAFT')
                <form method="POST" action="{{ route('process.goods.deliver', $app) }}" class="mt" data-confirm="Nyatakan barang telah diserahkan? Pastikan BAST & pakta integritas C.2 sudah diunggah.">@csrf<button class="btn primary">Nyatakan barang diserahkan</button><div class="hint">Memerlukan BAST bertanda tangan dan pakta integritas (tab Dokumen).</div></form>
            @endif
        @endif
        @if ($g && $g->status === 'DISERAHKAN' && $user->hasRole('BPKAD') && $s === Status::NPHD_SIGNED)
            <form method="POST" action="{{ route('process.goods.asset', $app) }}" class="stack mt">@csrf
                <div><label>Referensi pencatatan aset (KIB/BMD) <span class="req">*</span></label><input type="text" name="asset_ref" required></div>
                <button class="btn primary">Catat barang keluar dari aset daerah</button></form>
        @endif
    </div>
</div>
