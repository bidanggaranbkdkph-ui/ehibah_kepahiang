@extends('layouts.app')
@section('title', 'Dashboard Admin')
@section('heading', 'Dashboard Admin Sistem')
@section('content')
<section class="hero2">
    <div class="hero2-main">
        <img class="crest-lg" src="{{ asset('img/logo-kepahiang.png') }}" alt="Lambang Kabupaten Kepahiang">
        <div>
            <small>Selamat Datang di</small>
            <h1>E<i>-</i>Hibah</h1>
            <b class="sub">Panel Admin Sistem<br>Pemerintah Kabupaten Kepahiang</b>
            <p>Mengelola akun, data master, konfigurasi, dan memantau seluruh menu.</p>
        </div>
    </div>
    <aside class="hero2-card">
        <div class="date"><svg class="i i-lg"><use href="#i-calendar"/></svg><div><b>{{ now()->translatedFormat('l, d F Y') }}</b><small>{{ auth()->user()->roleLabel() }}</small></div></div>
        <blockquote>&ldquo;Hibah yang dikelola dengan baik adalah investasi untuk masa depan Kepahiang.&rdquo;<footer>&mdash; E-Hibah Kabupaten Kepahiang</footer></blockquote>
    </aside>
</section>
<div class="grid g4 kpis">
    <div class="card kpi2"><span class="tile c2"><svg class="i i-lg"><use href="#i-users"/></svg></span><div><small>Pengguna aktif</small><b>{{ $counts['pengguna'] }}</b><span class="up">akun</span></div></div>
    <div class="card kpi2"><span class="tile c3"><svg class="i i-lg"><use href="#i-users"/></svg></span><div><small>Menunggu validasi</small><b>{{ $counts['menunggu'] }}</b><span class="up">registrasi pemohon</span></div></div>
    <div class="card kpi2"><span class="tile {{ $counts['terkunci'] ? 'c7' : 'c5' }}"><svg class="i i-lg"><use href="#i-lock"/></svg></span><div><small>Akun terkunci</small><b>{{ $counts['terkunci'] }}</b><span class="{{ $counts['terkunci'] ? 'down' : 'up' }}">percobaan gagal</span></div></div>
    <div class="card kpi2"><span class="tile c1"><svg class="i i-lg"><use href="#i-users"/></svg></span><div><small>MFA aktif</small><b>{{ $counts['mfa'] }}</b><span class="up">akun</span></div></div>
</div>
<div class="grid g2 mt" style="margin-top:1rem">
    <div class="card">
        <div class="card-h"><h3>Registrasi menunggu validasi (SOP 1)</h3><span class="sp"></span><a class="btn sm" href="{{ route('admin.users.index', ['status' => 'PENDING']) }}">Semua</a></div>
        @forelse ($pending as $p)
            <div class="doc-item"><span class="doc-ic"><svg class="i"><use href="#i-user"/></svg></span>
                <div><b>{{ $p->name }}</b><span class="small muted">{{ $p->organization?->name }} · {{ $p->email }}</span></div>
                <form method="POST" action="{{ route('admin.users.validate', $p) }}" class="row" data-multi>@csrf
                    <button class="btn sm primary" name="decision" value="approve">Validasi</button>
                    <button class="btn sm danger" name="decision" value="reject" data-multi>Tolak</button></form></div>
        @empty <div class="empty small">Tidak ada registrasi yang menunggu.</div> @endforelse
    </div>
    <div class="card">
        <div class="card-h"><h3>Backup terakhir</h3><span class="sp"></span><a class="btn sm" href="{{ route('admin.backup.index') }}">Kelola</a></div>
        @if ($lastBackup)
            <dl class="def"><dt>Jenis</dt><dd>{{ $lastBackup->type }}</dd><dt>Status</dt><dd><span class="badge {{ $lastBackup->status === 'BERHASIL' ? 'green' : 'red' }}">{{ $lastBackup->status }}</span></dd>
                <dt>Waktu</dt><dd>{{ tgljam($lastBackup->started_at) }}</dd><dt>Diverifikasi</dt><dd>{{ $lastBackup->verified_at ? tgljam($lastBackup->verified_at) : 'belum' }}</dd></dl>
        @else <div class="alert warning"><svg class="i"><use href="#i-alert"/></svg>Belum ada backup. Jalankan backup pertama.</div> @endif
    </div>
</div>
@endsection
