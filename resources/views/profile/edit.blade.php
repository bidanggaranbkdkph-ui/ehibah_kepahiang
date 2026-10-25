@extends('layouts.app')
@section('title', 'Profil & keamanan')
@section('heading', 'Profil & Keamanan')
@section('content')
@php $org = $user->organization; @endphp
<div class="page-head"><div><h1>Profil & keamanan</h1><p>{{ $user->roleLabel() }} · {{ $org?->name }}</p></div></div>
<div class="grid g2">
    <div class="stack">
        <div class="card"><div class="card-h"><h3>Data akun</h3></div>
            <form method="POST" action="{{ route('profile.update') }}" class="stack">@csrf @method('PUT')
                <div><label>Nama</label><input type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
                <div><label>Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                <div><label>No. HP</label><input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"></div>
                <div><label>Jabatan</label><input type="text" name="position" value="{{ old('position', $user->position) }}"></div>
                <button class="btn primary">Simpan</button></form></div>

        <div class="card"><div class="card-h"><h3>Ganti kata sandi</h3></div>
            <form method="POST" action="{{ route('profile.password') }}" class="stack">@csrf @method('PUT')
                <div><label>Kata sandi saat ini</label><input type="password" name="current_password" required autocomplete="current-password"></div>
                <div><label>Kata sandi baru</label><input type="password" name="password" required autocomplete="new-password"><div class="hint">Minimal {{ \App\Support\Setting::int('security.password_min') }} karakter, huruf besar &amp; kecil, angka.</div></div>
                <div><label>Ulangi kata sandi baru</label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
                <button class="btn navy">Ganti kata sandi</button></form></div>

        <div class="card"><div class="card-h"><svg class="i"><use href="#i-lock"/></svg><h3>Verifikasi dua langkah (MFA)</h3><span class="sp"></span>
            <span class="badge {{ $user->mfa_enabled_at ? 'green' : 'gray' }}">{{ $user->mfa_enabled_at ? 'Aktif' : 'Nonaktif' }}</span></div>
            @if ($user->mfa_enabled_at)
                <p class="small muted">Aktif sejak {{ tgljam($user->mfa_enabled_at) }}.</p>
                @unless ($user->requiresMfa())
                    <form method="POST" action="{{ route('mfa.disable') }}" class="row" data-confirm="Nonaktifkan MFA?">@csrf<input type="password" name="password" placeholder="Kata sandi" required style="flex:1"><button class="btn danger">Nonaktifkan</button></form>
                @else <p class="small muted">Peran Anda wajib MFA.</p> @endunless
            @else
                <p class="small muted">Tambahkan lapisan keamanan dengan kode sekali pakai dari aplikasi autentikator.</p><a class="btn primary" href="{{ route('mfa.setup') }}">Aktifkan MFA</a>
            @endif
        </div>
    </div>

    @if ($user->hasRole('PEMOHON') && $org)
    <div class="card"><div class="card-h"><h3>Data lembaga</h3></div>
        <p class="small muted">Data rekening dan pengurus diwajibkan sebelum pengajuan hibah uang.</p>
        <form method="POST" action="{{ route('profile.organization') }}" class="form-grid">@csrf @method('PUT')
            <div class="full"><label>Nama lembaga</label><input type="text" value="{{ $org->name }}" disabled></div>
            <div class="full"><label>Alamat <span class="req">*</span></label><textarea name="address" rows="2" required>{{ old('address', $org->address) }}</textarea></div>
            <div><label>Kontak</label><input type="text" name="contact" value="{{ old('contact', $org->contact) }}"></div>
            <div><label>NPWP</label><input type="text" name="npwp" value="{{ old('npwp', $org->npwp) }}"></div>
            <div><label>No. akta / SK</label><input type="text" name="legal_doc_no" value="{{ old('legal_doc_no', $org->legal_doc_no) }}"></div>
            <div><label>Tanggal akta / SK</label><input type="date" name="legal_doc_date" value="{{ old('legal_doc_date', $org->legal_doc_date?->toDateString()) }}"></div>
            <div><label>Nama ketua <span class="req">*</span></label><input type="text" name="chair_name" value="{{ old('chair_name', $org->chair_name) }}" required></div>
            <div><label>Nama sekretaris</label><input type="text" name="secretary_name" value="{{ old('secretary_name', $org->secretary_name) }}"></div>
            <div><label>Bank</label><input type="text" name="bank_name" value="{{ old('bank_name', $org->bank_name) }}"></div>
            <div><label>No. rekening</label><input type="text" name="bank_account_no" value="{{ old('bank_account_no', $org->bank_account_no) }}"></div>
            <div class="full"><label>Atas nama rekening</label><input type="text" name="bank_account_name" value="{{ old('bank_account_name', $org->bank_account_name) }}"></div>
            <div class="full"><button class="btn primary">Simpan data lembaga</button></div>
        </form></div>
    @endif
</div>
@endsection
