@extends('layouts.guest')
@section('title', 'Registrasi pemohon')
@section('wrap', 'auth-box wide')
@section('content')
<div class="card auth-card">
    <h2>Registrasi pemohon hibah</h2>
    <p class="muted small">SOP 1 — akun baru akan divalidasi admin layanan sebelum dapat digunakan. Isi data lembaga dan pengurus dengan benar; dokumen legalitas diunggah saat mengajukan usulan.</p>
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <h3>Data lembaga</h3>
        <div class="form-grid">
            <div class="full"><label>Nama lembaga <span class="req">*</span></label><input type="text" name="org_name" value="{{ old('org_name') }}" required></div>
            <div><label>Kategori penerima <span class="req">*</span></label>
                <select name="recipient_category_id" required><option value="">— pilih —</option>
                    @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('recipient_category_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select><div class="hint">Menentukan dokumen wajib (Perbup Pasal 3–5).</div></div>
            <div><label>NPWP</label><input type="text" name="npwp" value="{{ old('npwp') }}"></div>
            <div class="full"><label>Alamat <span class="req">*</span></label><textarea name="address" rows="2" required>{{ old('address') }}</textarea></div>
            <div><label>Kontak lembaga</label><input type="text" name="contact" value="{{ old('contact') }}"></div>
            <div><label>No. akta / SK pengesahan</label><input type="text" name="legal_doc_no" value="{{ old('legal_doc_no') }}"></div>
            <div><label>Tanggal akta / SK</label><input type="date" name="legal_doc_date" value="{{ old('legal_doc_date') }}"></div>
            <div><label>Nama ketua <span class="req">*</span></label><input type="text" name="chair_name" value="{{ old('chair_name') }}" required></div>
            <div><label>Nama sekretaris</label><input type="text" name="secretary_name" value="{{ old('secretary_name') }}"></div>
        </div>
        <h3 class="mt">Akun pengurus</h3>
        <div class="form-grid">
            <div><label>Nama lengkap <span class="req">*</span></label><input type="text" name="name" value="{{ old('name') }}" required></div>
            <div><label>NIK <span class="req">*</span></label><input type="text" name="nip_nik" value="{{ old('nip_nik') }}" inputmode="numeric" required></div>
            <div><label>Email <span class="req">*</span></label><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
            <div><label>No. HP <span class="req">*</span></label><input type="tel" name="phone" value="{{ old('phone') }}" required></div>
            <div><label>Jabatan</label><input type="text" name="position" value="{{ old('position') }}"></div>
            <div></div>
            <div><label>Kata sandi <span class="req">*</span></label><input type="password" name="password" required autocomplete="new-password"><div class="hint">Minimal {{ \App\Support\Setting::int('security.password_min') }} karakter, huruf besar &amp; kecil, dan angka.</div></div>
            <div><label>Ulangi kata sandi <span class="req">*</span></label><input type="password" name="password_confirmation" required autocomplete="new-password"></div>
            <div class="full"><label class="check"><input type="checkbox" name="agree" value="1" required> <span>Saya menyatakan data di atas benar dan bertanggung jawab atas kebenarannya.</span></label></div>
        </div>
        <div class="row mt"><button class="btn primary" type="submit">Kirim registrasi</button><a class="btn" href="{{ route('login') }}">Batal</a></div>
    </form>
</div>
@endsection
