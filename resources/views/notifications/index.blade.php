@extends('layouts.app')
@section('title', 'Notifikasi')
@section('heading', 'Notifikasi')
@section('content')
<div class="page-head"><div><h1>Notifikasi</h1><p>Perubahan status, pengingat LPJ, dan permintaan tindakan.</p></div><div class="sp"></div>
    <form method="POST" action="{{ route('notifications.readAll') }}">@csrf<button class="btn">Tandai semua dibaca</button></form></div>
<div class="card">
    @forelse ($items as $n)
        @php $d = $n->data; $tone = ['info' => 'blue', 'success' => 'green', 'warning' => 'amber', 'danger' => 'red'][$d['level'] ?? 'info'] ?? 'blue'; @endphp
        <a href="{{ route('notifications.open', $n->id) }}" class="doc-item" style="text-decoration:none;color:inherit;{{ $n->read_at ? 'opacity:.7' : 'border-left:4px solid var(--em-2)' }}">
            <span class="doc-ic"><svg class="i"><use href="#i-bell"/></svg></span>
            <div><b>{{ $d['title'] ?? 'Notifikasi' }}</b><div class="small">{{ $d['message'] ?? '' }}</div><div class="small muted">{{ $d['reg_number'] ?? '' }} · {{ $n->created_at->diffForHumans() }}</div></div>
            <span class="badge {{ $tone }}">{{ $n->read_at ? 'dibaca' : 'baru' }}</span>
        </a>
    @empty <div class="empty"><svg class="i i-lg"><use href="#i-bell"/></svg><p>Belum ada notifikasi.</p></div> @endforelse
</div>
<div class="mt">{{ $items->links() }}</div>
@endsection
