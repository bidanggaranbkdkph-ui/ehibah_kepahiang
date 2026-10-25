@extends('layouts.app')
@section('title', $app->reg_number ?? 'Usulan')
@section('heading', 'Detail Usulan')
@section('content')
@php
    use App\Enums\Status;
    $user = auth()->user();
    $s = $app->status;
    $flow = Status::mainFlow();
    $terminalBad = in_array($s, [Status::REJECTED, Status::CANCELLED, Status::RETURNED], true);
    $histStatuses = $app->histories->pluck('to_status')->all();
    $reachedOrder = max(array_map(fn ($v) => Status::from($v)->order(), $histStatuses ?: ['DRAFT']));
    $curOrder = $s->order() >= 0 ? $s->order() : $reachedOrder;
    $tab = fn ($id, $label, $icon, $badge = null) => [$id, $label, $icon, $badge];
@endphp

<div class="page-head">
    <div style="min-width:0">
        <div class="row small muted"><a href="{{ route('applications.index') }}">Usulan</a> / <span class="mono">{{ $app->reg_number ?? 'draf' }}</span></div>
        <h1 style="margin-top:.2rem">{{ $app->title }}</h1>
        <div class="row">
            <x-status :status="$s"/>
            <span class="badge gray">{{ $app->grantType->name }}</span>
            <span class="badge gray">{{ $app->category->name }}</span>
            @if ($app->lpj_overdue)<span class="badge red">LPJ terlambat {{ $daysLate }} hari</span>@endif
            @if ($app->disbursement_scheme === 'FULL')<span class="badge gold">Pencairan 100% (pengecualian)</span>@endif
            @if ($app->revision_no)<span class="badge amber">Revisi ke-{{ $app->revision_no }}</span>@endif
        </div>
    </div>
    <div class="sp"></div>
    <div class="row">
        @if ($isApplicant && $s->isEditableByApplicant())<a class="btn" href="{{ route('applications.edit', $app) }}"><svg class="i"><use href="#i-edit"/></svg>Ubah</a>@endif
        @if ($app->recommendation && $user->hasRole('OPD', 'TAPD', 'BUPATI', 'VERIFIKATOR', 'INSPEKTORAT', 'BPKAD'))<a class="btn" target="_blank" href="{{ route('print', [$app, 'rekomendasi']) }}"><svg class="i"><use href="#i-print"/></svg>Rekomendasi</a>@endif
    </div>
</div>

{{-- Alur status --}}
<div class="card" style="margin-bottom:1rem">
    <div class="stepper" aria-label="Alur status">
        @foreach ($flow as $f)
            @php
                $cls = '';
                if ($terminalBad && $f->order() > $reachedOrder) { $cls = ''; }
                elseif ($f === $s) { $cls = 'now'; }
                elseif ($f->order() < $curOrder) { $cls = 'done'; }
            @endphp
            <div class="step {{ $cls }}"><div class="n">{{ $cls === 'done' ? '✓' : $loop->iteration }}</div>{{ $f->label() }}</div>
        @endforeach
        @if ($terminalBad)<div class="step bad now"><div class="n">!</div>{{ $s->label() }}</div>@endif
    </div>
    @if ($s === Status::RETURNED)<div class="alert warning" style="margin:.6rem 0 0"><svg class="i"><use href="#i-alert"/></svg><div><b>Dikembalikan untuk perbaikan.</b> {{ $app->histories->where('to_status', 'RETURNED')->last()?->note }} — perbaiki isian/dokumen lalu ajukan ulang.</div></div>@endif
    @if ($s === Status::REJECTED)<div class="alert error" style="margin:.6rem 0 0"><svg class="i"><use href="#i-x"/></svg><div><b>Usulan ditolak.</b> Alasan: {{ $app->rejection_reason }}. Pemohon dapat mengajukan usulan baru setelah memperbaiki kekurangan.</div></div>@endif
    @if ($s === Status::CANCELLED)<div class="alert error" style="margin:.6rem 0 0"><svg class="i"><use href="#i-x"/></svg><div><b>Usulan dibatalkan.</b> {{ $app->cancellation_reason }}</div></div>@endif
    @if ($dueSoon !== null && $dueSoon >= 0 && $dueSoon <= 14)<div class="alert warning" style="margin:.6rem 0 0"><svg class="i"><use href="#i-clock"/></svg><div>Tenggat LPJ <b>{{ tgl($app->lpj_due_date) }}</b> ({{ $dueSoon }} hari lagi).</div></div>@endif
</div>

<div class="grid g-main">
    <div style="min-width:0">
        <div class="tabs" data-tabs role="tablist">
            <button type="button" data-tab="ringkasan"><svg class="i"><use href="#i-file"/></svg>Ringkasan</button>
            <button type="button" data-tab="dokumen"><svg class="i"><use href="#i-folder"/></svg>Dokumen @php $miss = collect($checklists)->sum(fn ($c) => $c['progress']['total'] - $c['progress']['done']); @endphp @if ($miss)<span class="pill">{{ $miss }} kurang</span>@endif</button>
            <button type="button" data-tab="penilaian"><svg class="i"><use href="#i-check"/></svg>Penilaian</button>
            <button type="button" data-tab="penetapan"><svg class="i"><use href="#i-gavel"/></svg>Penetapan</button>
            @if ($s->order() >= Status::BUDGETED->order())<button type="button" data-tab="nphd"><svg class="i"><use href="#i-edit"/></svg>NPHD</button>@endif
            @if ($s->order() >= Status::NPHD_SIGNED->order())<button type="button" data-tab="penyaluran"><svg class="i"><use href="#i-wallet"/></svg>{{ $app->isMoney() ? 'Penyaluran' : 'Penyerahan' }}</button>@endif
            @if ($s->order() >= Status::DISBURSED->order())<button type="button" data-tab="lpj"><svg class="i"><use href="#i-book"/></svg>LPJ & Monev</button>@endif
            <button type="button" data-tab="koreksi"><svg class="i"><use href="#i-fix"/></svg>Koreksi @if ($app->corrections->where('status', 'DIAJUKAN')->count())<span class="pill">{{ $app->corrections->where('status', 'DIAJUKAN')->count() }}</span>@endif</button>
            <button type="button" data-tab="riwayat"><svg class="i"><use href="#i-clock"/></svg>Riwayat</button>
        </div>

        <div class="tab-pane" id="tab-ringkasan">@include('applications.partials.summary')</div>
        <div class="tab-pane" id="tab-dokumen">@include('applications.partials.documents')</div>
        <div class="tab-pane" id="tab-penilaian">@include('applications.partials.assessment')</div>
        <div class="tab-pane" id="tab-penetapan">@include('applications.partials.decision')</div>
        @if ($s->order() >= Status::BUDGETED->order())<div class="tab-pane" id="tab-nphd">@include('applications.partials.nphd')</div>@endif
        @if ($s->order() >= Status::NPHD_SIGNED->order())<div class="tab-pane" id="tab-penyaluran">@include($app->isMoney() ? 'applications.partials.disbursement' : 'applications.partials.goods')</div>@endif
        @if ($s->order() >= Status::DISBURSED->order())<div class="tab-pane" id="tab-lpj">@include('applications.partials.lpj')</div>@endif
        <div class="tab-pane" id="tab-koreksi">@include('applications.partials.corrections')</div>
        <div class="tab-pane" id="tab-riwayat">@include('applications.partials.history')</div>
    </div>

    <aside class="stack action-panel">
        @include('applications.partials.actions')
        <div class="card">
            <div class="card-h"><h3>Posisi usulan</h3></div>
            <dl class="def" style="grid-template-columns:120px 1fr">
                <dt>Tahap</dt><dd>{{ $s->phase() }}</dd>
                <dt>Ditangani</dt><dd>{{ $s->responsibleRoles() ? collect($s->responsibleRoles())->map(fn ($r) => config('ehibah.roles.'.$r))->implode(' / ') : '—' }}</dd>
                <dt>Diperbarui</dt><dd>{{ tgljam($app->status_changed_at) }}</dd>
                <dt>Diusulkan</dt><dd>{{ rupiah($app->amount_requested) }}</dd>
                @if ($app->amount_recommended)<dt>Direkomendasi</dt><dd>{{ rupiah($app->amount_recommended) }}</dd>@endif
                @if ($app->amount_approved)<dt>Ditetapkan</dt><dd><b>{{ rupiah($app->amount_approved) }}</b></dd>@endif
                @if ($app->isMoney() && $app->amount_approved)<dt>Tersalur</dt><dd>{{ rupiah($app->disbursedTotal()) }} <span class="muted small">({{ round($app->disbursedTotal() / max(1, $app->amount_approved) * 100) }}%)</span></dd>@endif
            </dl>
        </div>
        @if ($ceilingInfo && $perm('anggaran'))
        <div class="card"><div class="card-h"><h3>Pagu {{ $app->opd->name }}</h3></div>
            @php $pp = $ceilingInfo['ceiling'] ? round($ceilingInfo['used'] / $ceilingInfo['ceiling'] * 100) : 0; @endphp
            <div class="bar {{ $pp > 90 ? 'hot' : ($pp > 70 ? 'warn' : '') }}"><i style="width:{{ min(100, $pp) }}%"></i></div>
            <div class="small mt-s">Terpakai {{ rupiah($ceilingInfo['used']) }} dari {{ rupiah($ceilingInfo['ceiling']) }}</div>
            <div class="small"><b>Sisa {{ rupiah($ceilingInfo['remaining']) }}</b></div></div>
        @endif
    </aside>
</div>
@endsection
