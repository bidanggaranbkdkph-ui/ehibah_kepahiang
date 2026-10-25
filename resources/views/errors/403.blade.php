@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('title', 'Akses ditolak')
@section('heading', 'Akses ditolak')
@section('content')
<div class="card" style="max-width:620px;margin:2rem auto;text-align:center">
    <svg class="i i-lg" style="width:48px;height:48px;color:var(--red)"><use href="#i-lock"/></svg>
    <h2 style="margin-top:.6rem">Anda tidak berwenang mengakses halaman ini</h2>
    <p class="muted">{{ $message ?? 'Akses ditolak.' }}</p>
    <p class="small muted">Percobaan ini telah dicatat pada log akses. Bila Anda merasa seharusnya berwenang, hubungi admin layanan.</p>
    <a class="btn navy" href="{{ auth()->check() ? route('dashboard') : route('home') }}">Kembali</a>
</div>
@endsection
