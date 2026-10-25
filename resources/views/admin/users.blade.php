@extends('layouts.app')
@section('title', 'Pengguna')
@section('heading', 'Kelola Pengguna')
@section('content')
@php $badge = ['ACTIVE' => ['Aktif', 'green'], 'PENDING' => ['Menunggu validasi', 'amber'], 'SUSPENDED' => ['Ditangguhkan', 'red'], 'REJECTED' => ['Ditolak', 'gray']]; @endphp
<div class="page-head"><div><h1>Pengguna & validasi akun</h1><p>SOP 1: registrasi dan validasi akun oleh admin layanan. Admin tidak boleh merangkap peran proses hibah.</p></div></div>
<form method="GET" class="card" style="margin-bottom:1rem"><div class="grid filters end">
    <div><label>Cari</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / email"></div>
    <div><label>Status</label><select name="status"><option value="">Semua</option>@foreach ($badge as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div><label>Peran</label><select name="role"><option value="">Semua</option>@foreach ($roles as $r)<option value="{{ $r->code }}" @selected(request('role') === $r->code)>{{ $r->name }}</option>@endforeach</select></div>
    <button class="btn navy">Filter</button></div></form>

<div class="grid g-main">
<div class="card pad0"><div class="table-wrap"><table class="responsive">
    <thead><tr><th>Pengguna</th><th>Organisasi</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead><tbody>
    @foreach ($users as $u)
        <tr><td data-label="Pengguna"><b>{{ $u->name }}</b><div class="small muted">{{ $u->email }} {!! $u->mfa_enabled_at ? '· <span class="badge green">MFA</span>' : '' !!}</div></td>
            <td data-label="Organisasi" class="small">{{ $u->organization?->name }}</td>
            <td data-label="Peran">@foreach ($u->roles as $r)<span class="badge blue">{{ $r->code }}</span> @endforeach</td>
            <td data-label="Status"><span class="badge {{ $badge[$u->account_status][1] }}">{{ $badge[$u->account_status][0] }}</span>@if ($u->isLocked())<br><span class="badge red">terkunci</span>@endif</td>
            <td data-label="Login" class="small">{{ $u->last_login_at ? tgljam($u->last_login_at) : '—' }}</td>
            <td>
                @if ($u->account_status === 'PENDING')
                    <form method="POST" action="{{ route('admin.users.validate', $u) }}" class="row" data-multi>@csrf<button class="btn sm primary" name="decision" value="approve">Validasi</button><button class="btn sm danger" name="decision" value="reject">Tolak</button></form>
                @else
                    <details class="inline"><summary>Kelola</summary><div class="body">
                        <form method="POST" action="{{ route('admin.users.update', $u) }}" class="stack">@csrf @method('PUT')
                            <div><label>Status</label><select name="account_status"><option value="ACTIVE" @selected($u->account_status === 'ACTIVE')>Aktif</option><option value="SUSPENDED" @selected($u->account_status === 'SUSPENDED')>Tangguhkan</option></select></div>
                            <div><label>Organisasi</label><select name="organization_id">@foreach ($orgs as $o)<option value="{{ $o->id }}" @selected($u->organization_id === $o->id)>{{ \Illuminate\Support\Str::limit($o->name, 36) }}</option>@endforeach</select></div>
                            <div><label>Jabatan</label><input type="text" name="position" value="{{ $u->position }}"></div>
                            <div><label>Peran</label>@foreach ($roles as $r)<label class="check" style="margin:.2rem 0"><input type="checkbox" name="roles[]" value="{{ $r->code }}" @checked(in_array($r->code, $u->roleCodes()))><span>{{ $r->name }}</span></label>@endforeach</div>
                            <button class="btn sm primary">Simpan</button></form>
                        <div class="row mt-s">@foreach (['password' => 'Reset sandi', 'mfa' => 'Reset MFA', 'unlock' => 'Buka kunci'] as $w => $l)
                            <form method="POST" action="{{ route('admin.users.reset', $u) }}" data-confirm="{{ $l }} untuk {{ $u->name }}?">@csrf<input type="hidden" name="what" value="{{ $w }}"><button class="btn sm">{{ $l }}</button></form>@endforeach</div>
                    </div></details>
                @endif
            </td></tr>
    @endforeach
    </tbody></table></div></div>

<div class="card"><div class="card-h"><h3>Buat akun petugas</h3></div>
    <form method="POST" action="{{ route('admin.users.store') }}" class="stack">@csrf
        <div><label>Nama <span class="req">*</span></label><input type="text" name="name" required></div>
        <div><label>Email <span class="req">*</span></label><input type="email" name="email" required></div>
        <div><label>NIP</label><input type="text" name="nip_nik"></div>
        <div><label>Jabatan</label><input type="text" name="position"><div class="hint">Penandatangan NPHD harus berjabatan "Kepala …" dan berperan OPD.</div></div>
        <div><label>Organisasi <span class="req">*</span></label><select name="organization_id" required>@foreach ($orgs as $o)<option value="{{ $o->id }}">{{ \Illuminate\Support\Str::limit($o->name, 36) }}</option>@endforeach</select></div>
        <div><label>Peran <span class="req">*</span></label>@foreach ($roles->where('code', '!=', 'PEMOHON') as $r)<label class="check" style="margin:.2rem 0"><input type="checkbox" name="roles[]" value="{{ $r->code }}"><span>{{ $r->name }}</span></label>@endforeach</div>
        <button class="btn primary">Buat akun</button><div class="hint">Kata sandi sementara ditampilkan sekali dan wajib diganti.</div></form></div>
</div>
<div class="mt">{{ $users->links() }}</div>
@endsection
