@extends('layouts.app')
@section('title', 'Aktifkan MFA')
@section('heading', 'Verifikasi dua langkah (MFA)')
@section('content')
<div class="card" style="max-width:560px">
    <h2>Aktifkan verifikasi dua langkah</h2>
    <ol class="small">
        <li>Pasang aplikasi autentikator (Google Authenticator, Microsoft Authenticator, Aegis, dll.).</li>
        <li>Tambahkan akun secara manual dengan kunci rahasia di bawah (jenis: berbasis waktu / TOTP).</li>
        <li>Masukkan kode 6 digit yang tampil untuk mengonfirmasi.</li>
    </ol>
    <div class="card flat tone-navy">
        <div class="small muted">Kunci rahasia</div>
        <div class="mono" style="font-size:1.1rem;letter-spacing:2px;word-break:break-all">{{ $secret }}</div>
        <div class="small muted mt-s">URI: <span class="mono" style="word-break:break-all">{{ $uri }}</span></div>
    </div>
    <form method="POST" action="{{ route('mfa.enable') }}" class="stack mt">
        @csrf
        <div><label>Kode 6 digit</label><input type="text" name="code" inputmode="numeric" maxlength="7" required autocomplete="one-time-code"></div>
        <button class="btn primary">Aktifkan</button>
    </form>
</div>
@endsection
