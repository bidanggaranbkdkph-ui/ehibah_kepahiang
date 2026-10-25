@extends('layouts.app')
@section('title', 'Koreksi data')
@section('heading', 'Koreksi Data')
@section('content')
@php $badge = ['DIAJUKAN' => ['Menunggu', 'amber'], 'DISETUJUI' => ['Disetujui', 'green'], 'DITOLAK' => ['Ditolak', 'red']]; $user = auth()->user(); @endphp
<div class="page-head"><div><h1>Koreksi data</h1><p>Perubahan pada data yang telah dikunci: wajib beralasan, disetujui pihak lain (4-mata), dan tercatat nilai lama & baru. Pengajuan dilakukan dari tab "Koreksi" pada halaman usulan.</p></div></div>
<div class="card pad0"><div class="table-wrap"><table class="responsive">
    <thead><tr><th>Usulan</th><th>Data</th><th>Perubahan</th><th>Alasan</th><th>Pengaju</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse ($items as $c)
        <tr><td data-label="Usulan"><a href="{{ route('applications.show', $c->application_id) }}#koreksi">{{ $c->application->reg_number }}</a></td>
            <td data-label="Data" class="mono">{{ $c->entity }}.{{ $c->field }}</td>
            <td data-label="Perubahan">{{ \Illuminate\Support\Str::limit($c->old_value, 40) }} → <b>{{ \Illuminate\Support\Str::limit($c->new_value, 40) }}</b></td>
            <td data-label="Alasan">{{ \Illuminate\Support\Str::limit($c->reason, 70) }}</td><td data-label="Pengaju">{{ $c->requester->name }}<div class="small muted">{{ tgl($c->created_at, true) }}</div></td>
            <td data-label="Status"><span class="badge {{ $badge[$c->status][1] }}">{{ $badge[$c->status][0] }}</span></td>
            <td>@if ($svc->canDecide($c, $user))<a class="btn sm primary" href="{{ route('applications.show', $c->application_id) }}#koreksi">Putuskan</a>@endif</td></tr>
    @empty <tr><td colspan="7"><div class="empty">Belum ada permohonan koreksi.</div></td></tr> @endforelse
    </tbody></table></div></div>
<div class="mt">{{ $items->links() }}</div>
@endsection
