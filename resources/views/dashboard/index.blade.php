@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('content')
@php
    use App\Enums\Status;
    $maxOpd = max(1, $byOpd->max('n') ?? 1);
    $maxMonth = max(1, $monthly->max() ?? 1);
    $statusCount = fn ($s) => (int) ($byStatus[$s->value] ?? 0);
@endphp
<section class="hero2">
    <div class="hero2-main">
        <img class="crest-lg" src="{{ asset('img/logo-kepahiang.png') }}" alt="Lambang Kabupaten Kepahiang">
        <div>
            <small>Selamat Datang di</small>
            <h1>E<i>-</i>Hibah</h1>
            <b class="sub">Sistem Informasi Pengelolaan Hibah<br>Pemerintah Kabupaten Kepahiang</b>
            <p>Transparan, Akuntabel, dan Tepat Sasaran<br>untuk Pembangunan Daerah.</p>
        </div>
    </div>
    <aside class="hero2-card">
        <div class="date"><svg class="i i-lg"><use href="#i-calendar"/></svg><div><b>{{ now()->translatedFormat('l, d F Y') }}</b><small>{{ auth()->user()->roleLabel() }}</small></div></div>
        <blockquote>&ldquo;Hibah yang dikelola dengan baik adalah investasi untuk masa depan Kepahiang.&rdquo;<footer>&mdash; E-Hibah Kabupaten Kepahiang</footer></blockquote>
        <form method="GET" class="row"><label for="fy" class="small muted">Tahun anggaran</label>
            <select id="fy" name="fy" data-autosubmit style="width:auto">
                @foreach ($years as $y)<option value="{{ $y->id }}" @selected($fy?->id === $y->id)>TA {{ $y->year }}</option>@endforeach
            </select></form>
        @if ($isApplicant)<a class="btn primary block" href="{{ route('applications.create') }}"><svg class="i"><use href="#i-plus"/></svg>Usulan baru</a>@endif
    </aside>
</section>
@if ($isApplicant && ! auth()->user()->organization?->bank_account_no)
    <div class="alert warning"><svg class="i"><use href="#i-alert"/></svg><div>Lengkapi <a href="{{ route('profile.edit') }}">data rekening &amp; pengurus lembaga</a> sebelum mengajukan hibah uang.</div></div>
@endif

<div class="grid g4 kpis">
    <div class="card kpi2"><span class="tile c1"><svg class="i i-lg"><use href="#i-file"/></svg></span><div><small>Jumlah usulan</small><b>{{ number_format($kpi['usulan'], 0, ',', '.') }}</b><span class="up">TA {{ $fy?->year }}</span></div></div>
    <div class="card kpi2"><span class="tile c2"><svg class="i i-lg"><use href="#i-check"/></svg></span><div><small>Nilai usulan</small><b>{{ \App\Support\Fmt::rupiahShort($kpi['nilai_usulan']) }}</b><span class="up">total diajukan</span></div></div>
    <div class="card kpi2"><span class="tile c3"><svg class="i i-lg"><use href="#i-wallet"/></svg></span><div><small>Realisasi penyaluran</small><b>{{ \App\Support\Fmt::rupiahShort($kpi['realisasi']) }}</b><span class="up">{{ $kpi['realisasi_pct'] }}% dari {{ \App\Support\Fmt::rupiahShort($kpi['ditetapkan']) }} ditetapkan</span></div></div>
    <div class="card kpi2 {{ $kpi['tunggakan'] ? 'warn' : '' }}"><span class="tile {{ $kpi['tunggakan'] ? 'c7' : 'c4' }}"><svg class="i i-lg"><use href="#i-clock"/></svg></span><div><small>Tunggakan LPJ</small><b>{{ $kpi['tunggakan'] }}</b><span class="{{ $kpi['tunggakan'] ? 'down' : 'up' }}">{{ $kpi['tunggakan'] ? 'lewat batas waktu' : 'tidak ada tunggakan' }}</span></div></div>
</div>
<div class="grid g-main mt" style="margin-top:1rem">
    <div class="stack">
        <div class="card">
            <div class="card-h"><svg class="i"><use href="#i-check"/></svg><h3>Menunggu tindakan Anda</h3><span class="sp"></span><a class="btn sm" href="{{ route('queue') }}">Semua</a></div>
            @forelse ($tasks as $a)
                <a href="{{ route('applications.show', $a) }}" class="doc-item" style="text-decoration:none;color:inherit">
                    <span class="doc-ic"><svg class="i"><use href="#i-file"/></svg></span>
                    <div><b>{{ \Illuminate\Support\Str::limit($a->title, 60) }}</b><span class="small muted">{{ $a->reg_number ?? 'Draf' }} · {{ $a->applicant->name }} · {{ \App\Support\Fmt::rupiah($a->amount_requested) }}</span></div>
                    <x-status :status="$a->status"/>
                </a>
            @empty
                <div class="empty"><svg class="i i-lg"><use href="#i-check"/></svg><p>Tidak ada usulan yang menunggu tindakan Anda.</p></div>
            @endforelse
        </div>

        <div class="grid g2">
            <div class="card">
                <div class="card-h"><h3>Sebaran status</h3></div>
                @php $flow = array_filter(Status::cases(), fn ($s) => $statusCount($s) > 0); $mx = max(1, ...array_map($statusCount, $flow ?: [Status::DRAFT])); @endphp
                @forelse ($flow as $s)
                    <div class="hbar"><span class="lbl">{{ $s->label() }}</span><div class="bar {{ in_array($s, [Status::REJECTED, Status::CANCELLED]) ? 'hot' : '' }}"><i style="width:{{ $statusCount($s) / $mx * 100 }}%"></i></div><b>{{ $statusCount($s) }}</b></div>
                @empty <div class="empty small">Belum ada data.</div> @endforelse
            </div>
            <div class="card">
                <div class="card-h"><h3>Usulan per OPD</h3></div>
                @forelse ($byOpd as $o)
                    <div class="hbar"><span class="lbl" title="{{ $o->name }}">{{ $o->name }}</span><div class="bar"><i style="width:{{ $o->n / $maxOpd * 100 }}%"></i></div><b>{{ $o->n }}</b></div>
                @empty <div class="empty small">Belum ada data.</div> @endforelse
            </div>
        </div>

        @if ($monthly->count())
        <div class="card">
            <div class="card-h"><h3>Usulan diajukan per bulan</h3></div>
            <div class="vbars">@foreach ($monthly as $m => $n)<div><b>{{ $n }}</b><i style="height:{{ max(4, $n / $maxMonth * 100) }}%"></i><span>{{ \Illuminate\Support\Carbon::parse($m.'-01')->translatedFormat('M') }}</span></div>@endforeach</div>
        </div>
        @endif
    </div>

    <div class="stack">
        @if ($ceilings && $ceilings->count())
        <div class="card">
            <div class="card-h"><svg class="i"><use href="#i-wallet"/></svg><h3>Penggunaan pagu</h3></div>
            @foreach ($ceilings as $c)
                <div style="margin-bottom:.8rem"><div class="row small" style="justify-content:space-between"><b>{{ \Illuminate\Support\Str::limit($c['opd'], 30) }}</b><span>{{ $c['pct'] }}%</span></div>
                    <div class="bar {{ $c['pct'] > 90 ? 'hot' : ($c['pct'] > 70 ? 'warn' : '') }}"><i style="width:{{ min(100, $c['pct']) }}%"></i></div>
                    <div class="small muted">{{ \App\Support\Fmt::rupiahShort($c['used']) }} dari {{ \App\Support\Fmt::rupiahShort($c['ceiling']) }}</div></div>
            @endforeach
        </div>
        @endif
        @if ($corrections)
            <a href="{{ route('corrections.index') }}" class="card kpi gold" style="display:block;text-decoration:none"><small>Koreksi data menunggu</small><b>{{ $corrections }}</b><span>Buka antrean koreksi →</span></a>
        @endif
        <div class="card">
            <div class="card-h"><h3>Jalan pintas</h3></div>
            <div class="stack">
                <a class="btn block" href="{{ route('applications.index') }}"><svg class="i"><use href="#i-list"/></svg>Daftar usulan</a>
                @if (\App\Support\Permission::allows(auth()->user(), 'dashboard_laporan', 'V'))<a class="btn block" href="{{ route('reports.index') }}"><svg class="i"><use href="#i-chart"/></svg>Laporan &amp; ekspor</a>@endif
                <a class="btn block" href="{{ route('public.status') }}"><svg class="i"><use href="#i-search"/></svg>Cek status publik</a>
                <a class="btn block" href="{{ route('matrix') }}"><svg class="i"><use href="#i-lock"/></svg>Matriks hak akses</a>
            </div>
        </div>
    </div>
</div>
@include('partials.alur')
@endsection
