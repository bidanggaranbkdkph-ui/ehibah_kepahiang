@extends('layouts.app')
@section('title', 'Keputusan Bupati')
@section('heading', 'Keputusan Bupati')
@section('content')
<div class="page-head"><div><h1>Keputusan Bupati — daftar penerima hibah</h1><p>Perbup Pasal 7: daftar nama/alamat penerima serta besaran ditetapkan dalam Keputusan Bupati berdasarkan Perda APBD dan Perkada penjabaran. Penetapan penerima tidak dapat dilakukan tanpa nomor, tanggal, dan referensi anggaran.</p></div></div>
<div class="grid g-main">
    <div class="card pad0"><div class="table-wrap"><table class="responsive">
        <thead><tr><th>No. Keputusan</th><th>Tanggal</th><th>TA</th><th>Perda / Perkada</th><th class="num">Penerima</th><th>Daftar nominatif</th></tr></thead><tbody>
        @forelse ($decrees as $d)
            <tr><td data-label="No."><b>{{ $d->decree_no }}</b><div class="small muted">{{ $d->title }}</div></td><td data-label="Tanggal">{{ tgl($d->decree_date) }}</td><td data-label="TA">{{ $d->fiscalYear->year }}</td>
                <td data-label="Rujukan" class="small">{{ $d->perda_ref }}<br>{{ $d->perkada_ref }}</td><td data-label="Penerima" class="num">{{ $d->decisions_count }}</td>
                <td><a class="btn sm" href="{{ route('decrees.nominatif', [$d, 'format' => 'xlsx']) }}"><svg class="i"><use href="#i-download"/></svg>Excel</a> <a class="btn sm" href="{{ route('decrees.nominatif', [$d, 'format' => 'pdf']) }}"><svg class="i"><use href="#i-download"/></svg>PDF</a></td></tr>
        @empty <tr><td colspan="6"><div class="empty">Belum ada Keputusan Bupati.</div></td></tr> @endforelse
        </tbody></table></div></div>
    @if (\App\Support\Permission::allows(auth()->user(), 'penetapan', 'P'))
    <div class="card"><div class="card-h"><h3>Catat Keputusan baru</h3></div>
        <form method="POST" action="{{ route('decrees.store') }}" class="stack">@csrf
            <div><label>Tahun anggaran</label><select name="fiscal_year_id" required>@foreach ($years as $y)<option value="{{ $y->id }}">{{ $y->year }}</option>@endforeach</select></div>
            <div><label>Nomor keputusan <span class="req">*</span></label><input type="text" name="decree_no" required></div>
            <div><label>Tanggal <span class="req">*</span></label><input type="date" name="decree_date" required></div>
            <div><label>Judul <span class="req">*</span></label><input type="text" name="title" required></div>
            <div><label>Referensi Perda APBD <span class="req">*</span></label><input type="text" name="perda_ref" required></div>
            <div><label>Referensi Perkada penjabaran <span class="req">*</span></label><input type="text" name="perkada_ref" required></div>
            <button class="btn primary">Simpan keputusan</button></form></div>
    @endif
</div>
@endsection
