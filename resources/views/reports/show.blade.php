@extends('layouts.app')
@section('title', $data['title'])
@section('heading', 'Laporan')
@section('content')
@php $q = request()->query(); $showFilters = $slug !== 'audit'; @endphp
<div class="page-head">
    <div><div class="small muted"><a href="{{ route('reports.index') }}">Laporan</a> /</div><h1>{{ $data['title'] }}</h1><p>{{ count($data['rows']) }} baris · {{ $data['generated'] }}</p></div><div class="sp"></div>
    <div class="row">
        <a class="btn" href="{{ route('reports.export', array_merge(['slug' => $slug, 'format' => 'xlsx'], $q)) }}"><svg class="i"><use href="#i-download"/></svg>Excel</a>
        <a class="btn" href="{{ route('reports.export', array_merge(['slug' => $slug, 'format' => 'pdf'], $q)) }}"><svg class="i"><use href="#i-download"/></svg>PDF</a>
    </div>
</div>
<form method="GET" class="card" style="margin-bottom:1rem">
    <div class="grid g4">
        <div><label>Tahun anggaran</label><select name="fy"><option value="">Semua</option>@foreach ($years as $y)<option value="{{ $y->id }}" @selected(request('fy') == $y->id)>{{ $y->year }}</option>@endforeach</select></div>
        @if ($showFilters)
        <div><label>OPD</label><select name="opd"><option value="">Semua</option>@foreach ($opds as $o)<option value="{{ $o->id }}" @selected(request('opd') == $o->id)>{{ \Illuminate\Support\Str::limit($o->name, 30) }}</option>@endforeach</select></div>
        <div><label>Bentuk hibah</label><select name="type"><option value="">Semua</option>@foreach ($types as $t)<option value="{{ $t->id }}" @selected(request('type') == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        <div><label>Status</label><select name="status"><option value="">Semua</option>@foreach (\App\Enums\Status::cases() as $st)<option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>@endforeach</select></div>
        @endif
    </div>
    <div class="row mt-s"><button class="btn navy">Terapkan filter</button><a class="btn" href="{{ route('reports.show', $slug) }}">Reset</a></div>
</form>
@if (! empty($data['note']))<div class="alert info"><svg class="i"><use href="#i-info"/></svg>{{ $data['note'] }}</div>@endif
<div class="card pad0"><div class="table-wrap">
    <table>
        <thead><tr>@foreach ($data['columns'] as $k => [$label, $type])<th class="{{ $type === 'money' || $type === 'int' ? 'num' : '' }}">{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($data['rows'] as $row)
            <tr>@foreach ($data['columns'] as $k => [$label, $type])<td class="{{ in_array($type, ['money', 'int']) ? 'num' : '' }}">{{ $svc->formatCell($row[$k] ?? null, $type) }}</td>@endforeach</tr>
        @empty <tr><td colspan="{{ count($data['columns']) }}"><div class="empty">Tidak ada data untuk filter ini.</div></td></tr> @endforelse
        </tbody>
        @if (! empty($data['totals']) && count($data['rows']))
            <tfoot><tr>@foreach ($data['columns'] as $k => [$label, $type])<td class="{{ in_array($type, ['money', 'int']) ? 'num' : '' }}">@if ($loop->first)JUMLAH @elseif (isset($data['totals'][$k])){{ $svc->formatCell($data['totals'][$k], $type) }}@endif</td>@endforeach</tr></tfoot>
        @endif
    </table></div></div>
@endsection
