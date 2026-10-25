@extends('layouts.app')
@section('title', 'Pagu & alokasi')
@section('heading', 'Pagu & Alokasi Anggaran')
@section('content')
<div class="page-head"><div><h1>Pagu hibah per OPD</h1><p>Alokasi per usulan dikunci terhadap sisa pagu — usulan tidak dapat ditetapkan bila dana tidak tersedia.</p></div><div class="sp"></div>
    <form method="GET"><select name="fy" data-autosubmit style="width:auto">@foreach ($years as $y)<option value="{{ $y->id }}" @selected($fy?->id === $y->id)>TA {{ $y->year }}</option>@endforeach</select></form></div>
<div class="grid g-main">
    <div class="card pad0"><div class="table-wrap"><table class="responsive">
        <thead><tr><th>OPD</th><th>Kode rekening / DPA</th><th class="num">Pagu</th><th class="num">Dialokasikan</th><th class="num">Sisa</th><th style="min-width:130px">Penggunaan</th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr><td data-label="OPD"><b>{{ $r['c']->opd->name }}</b></td><td data-label="Rekening" class="small">{{ $r['c']->account_code }}<br>{{ $r['c']->dpa_ref }}</td>
                <td data-label="Pagu" class="num">{{ rupiah($r['c']->ceiling) }}</td><td data-label="Dialokasikan" class="num">{{ rupiah($r['used']) }}<div class="small muted">dikonfirmasi DPA {{ rupiah($r['confirmed']) }}</div></td>
                <td data-label="Sisa" class="num"><b>{{ rupiah($r['remaining']) }}</b></td>
                <td data-label="Penggunaan"><div class="bar {{ $r['pct'] > 90 ? 'hot' : ($r['pct'] > 70 ? 'warn' : '') }}"><i style="width:{{ min(100, $r['pct']) }}%"></i></div><div class="small muted">{{ $r['pct'] }}%</div></td></tr>
        @empty <tr><td colspan="6"><div class="empty">Belum ada pagu untuk tahun ini.</div></td></tr> @endforelse
        </tbody>
        @if ($rows->count())<tfoot><tr><td colspan="2">Total</td><td class="num">{{ rupiah($rows->sum(fn ($r) => $r['c']->ceiling)) }}</td><td class="num">{{ rupiah($rows->sum('used')) }}</td><td class="num">{{ rupiah($rows->sum('remaining')) }}</td><td></td></tr></tfoot>@endif
    </table></div></div>
    @if (\App\Support\Permission::allows(auth()->user(), 'anggaran', 'F'))
    <div class="card"><div class="card-h"><h3>Tetapkan / ubah pagu</h3></div>
        <form method="POST" action="{{ route('budget.store') }}" class="stack">@csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $fy?->id }}">
            <div><label>OPD <span class="req">*</span></label><select name="opd_id" required>@foreach ($opds as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></div>
            <div><label>Pagu (Rp) <span class="req">*</span></label><input type="number" name="ceiling" min="0" required></div>
            <div><label>Kode rekening</label><input type="text" name="account_code"></div>
            <div><label>Referensi DPA</label><input type="text" name="dpa_ref"></div>
            <div><label>Catatan</label><input type="text" name="notes"></div>
            <button class="btn primary">Simpan pagu</button><div class="hint">Pagu baru tidak boleh lebih kecil dari dana yang sudah dialokasikan. Perubahan tercatat pada audit.</div></form></div>
    @endif
</div>
@endsection
