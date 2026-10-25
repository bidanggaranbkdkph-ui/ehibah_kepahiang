@extends('layouts.guest')
@section('title', 'Cek status usulan')
@section('content')
<div class="card auth-card">
    <h2>Cek status usulan</h2>
    <p class="muted small">Masukkan nomor register usulan (contoh: <span class="mono">EH/2026/DISDIKBUD/0001</span>). Hanya status dan tahap yang ditampilkan.</p>
    <form method="GET" action="{{ route('public.status') }}" class="row">
        <input type="search" name="reg" value="{{ $reg }}" placeholder="Nomor register" style="flex:1" required>
        <button class="btn primary">Cek</button>
    </form>
    @if ($result === false)
        <div class="alert warning mt"><svg class="i"><use href="#i-alert"/></svg>Nomor register tidak ditemukan.</div>
    @elseif ($result)
        <div class="card flat mt tone-green">
            <dl class="def">
                <dt>No. register</dt><dd class="mono">{{ $result['reg'] }}</dd>
                <dt>Status</dt><dd><x-status :status="$result['status']"/></dd>
                <dt>Tahap</dt><dd>{{ $result['phase'] }}</dd>
                <dt>Diperbarui</dt><dd>{{ $result['updated'] }}</dd>
            </dl>
        </div>
    @endif
</div>
@endsection
