@extends('layouts.app')
@section('title', 'Laporan')
@section('heading', 'Laporan')
@section('content')
<div class="page-head"><div><h1>Laporan</h1><p>Sepuluh laporan standar (Blueprint Bab 9.2). Setiap laporan dapat difilter dan diekspor ke Excel atau PDF; cakupan baris mengikuti hak akses Anda.</p></div></div>
<div class="grid g3">
    @foreach ($catalog as $slug => [$title, $sub])
        <a class="card" href="{{ route('reports.show', $slug) }}" style="text-decoration:none;color:inherit">
            <div class="row"><span class="doc-ic" style="background:var(--em-l);color:var(--em)"><b>{{ $loop->iteration }}</b></span><div><b style="color:var(--navy)">{{ $title }}</b><div class="small muted">{{ $sub }}</div></div></div>
        </a>
    @endforeach
</div>
@endsection
