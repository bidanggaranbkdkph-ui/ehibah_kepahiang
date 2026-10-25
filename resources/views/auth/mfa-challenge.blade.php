@extends('layouts.guest')
@section('title', 'Verifikasi dua langkah')
@section('content')
<div class="card auth-card">
    <h2>Verifikasi dua langkah</h2>
    <p class="muted small">Masukkan 6 digit kode dari aplikasi autentikator Anda.</p>
    <form method="POST" action="{{ route('mfa.verify') }}" class="stack">
        @csrf
        <div><label>Kode</label><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus required class="@error('code') is-invalid @enderror"></div>
        <button class="btn primary block">Verifikasi</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt">@csrf<button class="btn block">Keluar</button></form>
</div>
@endsection
