@extends('layouts.app')
@section('title', 'Akun Demo')
@section('heading', 'Akun Demo')
@section('content')
<div class="page-head"><div><h1>Akun demo</h1><p>Akun contoh untuk mencoba setiap peran. Halaman ini hanya muncul di lingkungan non-produksi.</p></div></div>
<div class="alert info"><svg class="i"><use href="#i-info"/></svg><div>Kata sandi bawaan akun demo: <span class="mono">{{ $password }}</span>. Akun yang kata sandinya sudah diubah (misalnya Admin) memakai kata sandi barunya.</div></div>
<div class="card pad0"><div class="table-wrap">
    <table class="responsive">
        <thead><tr><th>Email</th><th>Nama</th><th>Peran</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td data-label="Email"><span class="mono">{{ $u->email }}</span></td>
                <td data-label="Nama">{{ $u->name }}</td>
                <td data-label="Peran">{{ $u->roleLabel() }}</td>
                <td data-label="Status"><span class="badge {{ $u->is_active ? 'green' : 'gray' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4"><div class="empty small">Belum ada akun demo. Jalankan <span class="mono">php artisan db:seed</span>.</div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection