@extends('layouts.app')
@section('title', 'Audit trail')
@section('heading', 'Audit Trail')
@section('content')
<div class="page-head"><div><h1>Audit trail</h1><p>Setiap aktivitas penting tercatat dan <b>tidak dapat diubah atau dihapus oleh siapa pun</b> (append-only + rantai hash). Kategori terlihat: {{ implode(', ', $cats) }}.</p></div><div class="sp"></div>
    @if ($canVerify)<form method="POST" action="{{ route('audit.verify') }}">@csrf<button class="btn navy"><svg class="i"><use href="#i-shield"/></svg>Verifikasi keutuhan rantai</button></form>@endif</div>
<form method="GET" class="card" style="margin-bottom:1rem"><div class="grid g4" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div><label>Kategori</label><select name="category"><option value="">Semua</option>@foreach ($cats as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div><label>Aksi</label><input type="text" name="action" value="{{ request('action') }}" placeholder="mis. STATUS_APPROVE"></div>
    <div><label>Pengguna</label><input type="text" name="user" value="{{ request('user') }}"></div>
    <div><label>ID usulan</label><input type="number" name="app" value="{{ request('app') }}"></div>
    <div><label>Dari</label><input type="date" name="from" value="{{ request('from') }}"></div>
    <div><label>Sampai</label><input type="date" name="to" value="{{ request('to') }}"></div></div>
    <div class="row mt-s"><button class="btn navy">Filter</button><a class="btn" href="{{ route('audit.index') }}">Reset</a></div></form>
<div class="card pad0"><div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Waktu</th><th>Kat.</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Perubahan</th><th>IP</th><th>Hash</th></tr></thead><tbody>
    @forelse ($logs as $l)
        <tr><td class="mono small">{{ $l->id }}</td><td class="nowrap small">{{ tgljam($l->created_at) }}</td><td><span class="badge {{ ['PROSES' => 'blue', 'AKSES' => 'violet', 'SISTEM' => 'gold'][$l->category] ?? 'gray' }}">{{ $l->category }}</span></td>
            <td class="small">{{ $l->user_name ?? 'Sistem' }}<div class="muted">{{ $l->role_code }}</div></td><td class="mono small">{{ $l->action }}</td>
            <td class="small">{{ $l->entity_type }} {{ $l->entity_id ? '#'.$l->entity_id : '' }}@if ($l->application_id)<br><a href="{{ route('applications.show', $l->application_id) }}">usulan #{{ $l->application_id }}</a>@endif</td>
            <td class="small" style="max-width:320px;word-break:break-word">@if ($l->old_values)<span class="muted">dari</span> {{ \Illuminate\Support\Str::limit($l->old_values, 140) }}<br>@endif @if ($l->new_values)<span class="muted">menjadi</span> {{ \Illuminate\Support\Str::limit($l->new_values, 160) }}@endif</td>
            <td class="small mono">{{ $l->ip }}</td><td class="mono small" title="{{ $l->hash }}">{{ substr($l->hash, 0, 8) }}…</td></tr>
    @empty <tr><td colspan="9"><div class="empty">Tidak ada catatan.</div></td></tr> @endforelse
    </tbody></table></div></div>
<div class="mt">{{ $logs->links() }}</div>
@endsection
