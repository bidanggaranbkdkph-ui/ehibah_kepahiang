@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="card auth-card"><div class="login-banner" role="img" aria-label="Kabupaten Kepahiang, Bumi Sehasen"></div>
    <div class="row" style="margin-bottom:1rem"><img src="{{ asset('img/logo-kepahiang.png') }}" alt="Lambang Kabupaten Kepahiang" width="36" height="46" style="object-fit:contain"><div><h2 style="margin:0">Masuk ke E-Hibah</h2><span class="muted small">Gunakan akun yang telah divalidasi</span></div></div>
    <form method="POST" action="{{ route('login.store') }}" class="stack">
        @csrf
        <div><label for="email">Email / nama pengguna</label><input id="email" type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="@error('email') is-invalid @enderror"></div>
        <div><label for="password">Kata sandi</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn primary block" type="submit">Masuk</button>
    </form>
    <hr>
    <p class="small muted center" style="margin:0">Belum punya akun? <a href="{{ route('register') }}">Daftarkan lembaga Anda</a></p>
</div>
@endsection
