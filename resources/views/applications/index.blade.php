@extends('layouts.app')
@section('title', 'Usulan hibah')
@section('heading', 'Usulan Hibah')
@section('content')
<div class="page-head">
    <div><h1>Usulan hibah</h1><p>Hanya usulan dalam cakupan akses Anda yang ditampilkan.</p></div><div class="sp"></div>
    @if (auth()->user()->hasRole('PEMOHON'))<a class="btn primary" href="{{ route('applications.create') }}"><svg class="i"><use href="#i-plus"/></svg>Usulan baru</a>@endif
</div>

<form method="GET" class="card" style="margin-bottom:1rem">
    <div class="grid filters">
        <div><label for="q">Cari</label><input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Judul, no. register, pemohon"></div>
        <div><label>Status</label><select name="status"><option value="">Semua</option>@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div><label>Tahun</label><select name="fy"><option value="">Semua</option>@foreach ($years as $y)<option value="{{ $y->id }}" @selected(request('fy') == $y->id)>{{ $y->year }}</option>@endforeach</select></div>
        <div><label>OPD</label><select name="opd"><option value="">Semua</option>@foreach ($opds as $o)<option value="{{ $o->id }}" @selected(request('opd') == $o->id)>{{ \Illuminate\Support\Str::limit($o->name, 28) }}</option>@endforeach</select></div>
    </div>
    <div class="row mt-s"><button class="btn navy"><svg class="i"><use href="#i-search"/></svg>Terapkan</button><a class="btn" href="{{ route('applications.index') }}">Reset</a></div>
</form>

<div class="card pad0">
    <div class="table-wrap">
        <table class="responsive">
            <thead><tr><th>No. Register</th><th>Usulan</th><th>Pemohon</th><th>OPD</th><th>Jenis</th><th class="num">Nilai usulan</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($items as $a)
                <tr>
                    <td data-label="No. Register" class="mono nowrap">{{ $a->reg_number ?? '— draf —' }}</td>
                    <td data-label="Usulan"><a href="{{ route('applications.show', $a) }}"><b>{{ \Illuminate\Support\Str::limit($a->title, 70) }}</b></a></td>
                    <td data-label="Pemohon">{{ $a->applicant->name }}</td>
                    <td data-label="OPD">{{ $a->opd->name }}</td>
                    <td data-label="Jenis">{{ $a->grantType->name }}</td>
                    <td data-label="Nilai usulan" class="num">{{ rupiah($a->amount_requested) }}</td>
                    <td data-label="Status"><x-status :status="$a->status"/>@if ($a->lpj_overdue)<br><span class="badge red">LPJ terlambat</span>@endif</td>
                    <td><a class="btn sm" href="{{ route('applications.show', $a) }}">Buka</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><svg class="i i-lg"><use href="#i-file"/></svg><p>Tidak ada usulan yang cocok.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt">{{ $items->links() }}</div>
@endsection
