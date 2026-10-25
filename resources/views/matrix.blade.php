@extends('layouts.app')
@section('title', 'Matriks hak akses')
@section('heading', 'Matriks Hak Akses')
@section('content')
@php
    use App\Support\Permission as P;
    $me = auth()->user()->roleCodes();
    $names = P::LEVELS;
    $short = ['F' => 'Kelola penuh', 'P' => 'Proses / verifikasi', 'V' => 'Lihat saja', 'N' => 'Tidak ada akses'];
@endphp
<div class="page-head"><div>
    <h1>Matriks hak akses (peran × modul)</h1>
    <p>@if ($canEdit) Sebagai Admin Sistem, Anda dapat mengatur tingkat akses setiap peran pada setiap modul. Perubahan berlaku segera dan tercatat pada log sistem. @else Setiap pengguna hanya dapat melihat dan mengubah data sesuai perannya. Pembatasan dilakukan di server dan setiap penolakan tercatat pada audit trail. Kolom peran Anda disorot. @endif</p>
</div></div>

@if ($canEdit)
<form method="POST" action="{{ route('matrix.update') }}">@csrf @method('PUT')
@endif
<div class="card pad0"><div class="table-wrap">
    <table class="mx">
        <thead><tr><th style="min-width:230px">Modul / fitur</th>@foreach ($roles as $code => $name)<th style="{{ in_array($code, $me) ? 'background:var(--em)' : '' }}">{{ $name }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($matrix as $group => $rows)
            <tr class="grp"><td colspan="{{ count($roles) + 1 }}">{{ $group }}</td></tr>
            @foreach ($rows as $r)
                <tr><td><b>{{ $r['label'] }}</b></td>
                @foreach ($roles as $code => $name)
                    @php $lvl = $r['levels'][$code]; $locked = P::isLocked($r['feature'], $code); $changed = $lvl !== P::defaultLevel($code, $r['feature']); @endphp
                    <td style="{{ in_array($code, $me) ? 'background:var(--em-ll)!important' : '' }}">
                        @if ($canEdit && ! $locked)
                            <select class="lvl lvl-{{ $lvl }} {{ $changed ? 'changed' : '' }}" name="level[{{ $r['feature'] }}][{{ $code }}]" aria-label="{{ $r['label'] }} — {{ $name }}">
                                @foreach ($short as $k => $t)<option value="{{ $k }}" @selected($lvl === $k)>{{ $t }}</option>@endforeach
                            </select>
                        @else
                            <i class="sym s-{{ $lvl }}" title="{{ $names[$lvl] }}{{ $locked ? ' (terkunci)' : '' }}"></i>@if ($locked && $canEdit)<span class="lock" title="Terkunci agar akses sistem tidak hilang"><svg class="i"><use href="#i-lock"/></svg></span>@endif
                        @endif
                    </td>
                @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table></div></div>

@if ($canEdit)
    <div class="row mt">
        <button class="btn primary" type="submit"><svg class="i"><use href="#i-check"/></svg>Simpan perubahan hak akses</button>
        <span class="small muted">Sel bertanda <span class="changed-chip">oranye</span> berbeda dari pengaturan bawaan.</span>
    </div>
</form>
<form method="POST" action="{{ route('matrix.reset') }}" class="mt-s" data-confirm="Kembalikan seluruh hak akses ke pengaturan bawaan?">@csrf
    <button class="btn danger sm" type="submit">Kembalikan ke bawaan</button>
</form>
@endif

<div class="row mt small">
    <span><i class="sym s-F"></i> Kelola penuh (buat, ubah, setujui)</span><span><i class="sym s-P"></i> Proses / verifikasi</span><span><i class="sym s-V"></i> Lihat saja</span><span><i class="sym s-N"></i> Tidak ada akses</span>
</div>
<div class="alert info mt"><svg class="i"><use href="#i-info"/></svg><div><b>Cakupan data (row-level):</b> Pemohon hanya melihat usulan lembaganya; OPD hanya usulan di OPD-nya; Tim Verifikasi lintas OPD sejak verifikasi administrasi; TAPD/Bupati sejak direkomendasikan; BPKAD sejak pertimbangan TAPD; Inspektorat seluruh usulan yang diajukan; Admin Sistem seluruh usulan yang telah diajukan. Hak akses di atas mengatur menu dan fungsi yang tersedia; tombol tahapan proses tetap mengikuti peran akun, dan Admin Sistem tidak boleh merangkap peran proses (pemisahan tugas). Akun sendiri, serta Kelola Pengguna dan Konfigurasi untuk Admin, dikunci agar akses sistem tidak hilang.</div></div>
@endsection