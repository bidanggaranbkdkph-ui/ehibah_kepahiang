@extends('layouts.app')
@section('title', 'Monitoring & evaluasi')
@section('heading', 'Monitoring & Evaluasi')
@section('content')
<div class="page-head"><div><h1>Monitoring & evaluasi</h1><p>Pasal 21: hasil disampaikan kepada Bupati dengan tembusan Inspektorat. Catatan baru ditambahkan dari halaman usulan (tab LPJ & Monev).</p></div><div class="sp"></div>
    <form method="GET" class="row"><select name="status" data-autosubmit style="width:auto"><option value="">Semua tindak lanjut</option><option value="OPEN" @selected(request('status') === 'OPEN')>Terbuka</option><option value="SELESAI" @selected(request('status') === 'SELESAI')>Selesai</option></select></form></div>
<div class="card pad0"><div class="table-wrap"><table class="responsive">
    <thead><tr><th>Tanggal</th><th>Usulan</th><th>Petugas</th><th>Temuan</th><th>Tindak lanjut</th><th>Status</th></tr></thead><tbody>
    @forelse ($records as $m)
        <tr><td data-label="Tanggal" class="nowrap">{{ tgl($m->visited_at, true) }}</td>
            <td data-label="Usulan"><a href="{{ route('applications.show', $m->application_id) }}#lpj"><b>{{ $m->application->reg_number }}</b></a><div class="small muted">{{ $m->application->applicant->name }}</div></td>
            <td data-label="Petugas">{{ $m->officer->name }}</td><td data-label="Temuan">{{ \Illuminate\Support\Str::limit($m->findings, 90) }}</td><td data-label="Tindak lanjut">{{ \Illuminate\Support\Str::limit($m->follow_up ?? '—', 70) }}</td>
            <td data-label="Status"><span class="badge {{ $m->follow_up_status === 'OPEN' ? 'amber' : 'green' }}">{{ $m->follow_up_status === 'OPEN' ? 'Terbuka' : 'Selesai' }}</span></td></tr>
    @empty <tr><td colspan="6"><div class="empty">Belum ada catatan monitoring.</div></td></tr> @endforelse
    </tbody></table></div></div>
<div class="mt">{{ $records->links() }}</div>
@endsection
