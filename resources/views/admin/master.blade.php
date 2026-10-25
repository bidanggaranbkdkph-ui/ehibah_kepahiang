@extends('layouts.app')
@section('title', $def['label'])
@section('heading', 'Data Master')
@section('content')
@php $canEdit = \App\Support\Permission::allows(auth()->user(), 'data_master', 'F');
    $field = function ($k, $f, $row = null) use ($options) {
        $name = $k; $val = old($k, $row?->$k);
        if ($val instanceof \DateTimeInterface) $val = $val->format('Y-m-d');
        return [$name, $val];
    }; @endphp
<div class="page-head"><div><h1>{{ $def['label'] }}</h1><p>Perubahan data master tercatat pada log sistem.</p></div></div>
<div class="tabs" style="flex-wrap:wrap">@foreach ($all as $k => $d)<a href="{{ route('admin.master.index', $k) }}" style="padding:.6rem .9rem;font-weight:600;text-decoration:none;{{ $k === $entity ? 'color:var(--navy);border-bottom:3px solid var(--em-2)' : 'color:var(--mut)' }}">{{ $d['label'] }}</a>@endforeach</div>

<div class="grid g-main">
<div class="card pad0"><div class="table-wrap"><table>
    <thead><tr>@foreach ($def['fields'] as $k => $f)<th>{{ $f[0] }}</th>@endforeach<th></th></tr></thead><tbody>
    @foreach ($rows as $row)
        <tr>@foreach ($def['fields'] as $k => $f)
            <td class="small">@php $v = $row->$k; @endphp
                @if ($f[1] === 'bool')<span class="badge {{ $v ? 'green' : 'gray' }}">{{ $v ? 'Ya' : 'Tidak' }}</span>
                @elseif ($f[1] === 'select' && isset($options[$k][(string) $v])){{ $options[$k][(string) $v] }}
                @elseif ($v instanceof \DateTimeInterface){{ tgl($v, true) }}
                @else{{ \Illuminate\Support\Str::limit((string) $v, 60) }}@endif</td>@endforeach
            <td>@if ($canEdit)<details class="inline" style="min-width:90px"><summary>Ubah</summary><div class="body" style="min-width:260px">
                <form method="POST" action="{{ route('admin.master.update', [$entity, $row->id]) }}" class="stack">@csrf @method('PUT')
                    @foreach ($def['fields'] as $k => $f)
                        @if ($f[1] === 'bool')<label class="check"><input type="checkbox" name="{{ $k }}" value="1" @checked($row->$k)><span>{{ $f[0] }}</span></label>
                        @elseif ($f[1] === 'select')<div><label>{{ $f[0] }}</label><select name="{{ $k }}">@foreach ($options[$k] as $ov => $ol)<option value="{{ $ov }}" @selected((string) $row->$k === (string) $ov)>{{ $ol }}</option>@endforeach</select></div>
                        @else<div><label>{{ $f[0] }}</label><input type="{{ $f[1] === 'date' ? 'date' : ($f[1] === 'number' ? 'number' : 'text') }}" name="{{ $k }}" value="{{ $row->$k instanceof \DateTimeInterface ? $row->$k->format('Y-m-d') : $row->$k }}"></div>@endif
                    @endforeach
                    <button class="btn sm primary">Simpan</button></form></div></details>@endif</td></tr>
    @endforeach
    </tbody></table></div></div>

@if ($canEdit)
<div class="card"><div class="card-h"><h3>Tambah data</h3></div>
    <form method="POST" action="{{ route('admin.master.store', $entity) }}" class="stack">@csrf
        @foreach ($def['fields'] as $k => $f)
            @if ($f[1] === 'bool')<label class="check"><input type="checkbox" name="{{ $k }}" value="1" checked><span>{{ $f[0] }}</span></label>
            @elseif ($f[1] === 'select')<div><label>{{ $f[0] }}</label><select name="{{ $k }}">@foreach ($options[$k] as $ov => $ol)<option value="{{ $ov }}">{{ $ol }}</option>@endforeach</select></div>
            @else<div><label>{{ $f[0] }}</label><input type="{{ $f[1] === 'date' ? 'date' : ($f[1] === 'number' ? 'number' : 'text') }}" name="{{ $k }}" value="{{ old($k) }}"></div>@endif
        @endforeach
        <button class="btn primary">Tambah</button></form></div>
@endif
</div>
@endsection
