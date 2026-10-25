@extends('layouts.app')
@section('title', 'Konfigurasi')
@section('heading', 'Konfigurasi Sistem')
@section('content')
@php $canEdit = \App\Support\Permission::allows(auth()->user(), 'konfigurasi', 'F'); $f = fn ($k) => \App\Http\Controllers\Admin\SettingController::field($k); @endphp
<div class="page-head"><div><h1>Konfigurasi aturan bisnis & keamanan</h1><p>Nilai bawaan berasal dari regulasi (Perbup Hibah). Perubahan tercatat pada log sistem dengan nilai lama dan baru.</p></div></div>
<form method="POST" action="{{ route('admin.settings.update') }}" class="card">@csrf @method('PUT')
    <div class="form-grid">
        @foreach ($meta as $k => [$label, $type, $help])
            <div>
                @if ($type === 'bool')
                    <label class="check"><input type="hidden" name="{{ $f($k) }}" value="0"><input type="checkbox" name="{{ $f($k) }}" value="1" @checked($values[$k]) @disabled(! $canEdit)><span><b>{{ $label }}</b></span></label>
                @else
                    <label>{{ $label }}</label><input type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $f($k) }}" value="{{ old($f($k), $values[$k]) }}" @disabled(! $canEdit)>
                @endif
                @if ($help)<div class="hint">{{ $help }}</div>@endif
            </div>
        @endforeach
    </div>
    @if ($canEdit)<div class="mt"><button class="btn primary">Simpan konfigurasi</button></div>@endif
</form>
@endsection
