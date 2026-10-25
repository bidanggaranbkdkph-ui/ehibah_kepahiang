@php
    use App\Support\Permission as P;
    $u = auth()->user();
    $unread = $u->unreadNotifications()->count();
    $isAdminOnly = $u->hasRole('ADMIN') && count($u->roleCodes()) === 1;
    $is = fn (...$p) => request()->routeIs(...$p);
    $nav = [];
    $nav['Utama'][] = ['dashboard', 'i-home', 'Beranda', 'dashboard', null];
    $nav['Utama'][] = ['applications.index', 'i-file', $u->hasRole('PEMOHON') ? 'Usulan Saya' : 'Usulan Hibah', 'applications.*', null];
    if ($u->hasRole('PEMOHON')) $nav['Utama'][] = ['applications.create', 'i-plus', 'Buat Usulan Baru', 'applications.create', null];
    if (! $u->hasRole('PEMOHON')) $nav['Utama'][] = ['queue', 'i-check', 'Antrean Tindakan', 'queue', null];
    if (P::allows($u, 'penetapan', 'V')) $nav['Penetapan & Anggaran'][] = ['decrees.index', 'i-gavel', 'Keputusan Bupati', 'decrees.*', null];
    if (P::allows($u, 'anggaran', 'V')) $nav['Penetapan & Anggaran'][] = ['budget.index', 'i-wallet', 'Pagu & Alokasi', 'budget.*', null];
    if (P::allows($u, 'monev', 'V')) $nav['Pengawasan'][] = ['monev.index', 'i-eye', 'Monitoring & Evaluasi', 'monev.*', null];
    if (P::allows($u, 'koreksi_data', 'V')) $nav['Pengawasan'][] = ['corrections.index', 'i-fix', 'Koreksi Data', 'corrections.*', null];
    if (P::allows($u, 'dashboard_laporan', 'V')) $nav['Pengawasan'][] = ['reports.index', 'i-chart', 'Laporan', 'reports.*', null];
    if (P::allows($u, 'audit_trail', 'V') || P::allows($u, 'log_sistem', 'V')) $nav['Pengawasan'][] = ['audit.index', 'i-shield', $isAdminOnly ? 'Log Sistem' : 'Audit Trail', 'audit.*', null];
    if (P::allows($u, 'kelola_pengguna', 'V')) $nav['Administrasi'][] = ['admin.users.index', 'i-users', 'Pengguna', 'admin.users.*', null];
    if (P::allows($u, 'data_master', 'V')) $nav['Administrasi'][] = [['admin.master.index', 'organisasi'], 'i-db', 'Data Master', 'admin.master.*', null];
    if (P::allows($u, 'konfigurasi', 'V')) $nav['Administrasi'][] = ['admin.settings.index', 'i-settings', 'Konfigurasi', 'admin.settings.*', null];
    if (! app()->environment('production') && $u->hasRole('ADMIN') && P::allows($u, 'kelola_pengguna', 'V')) $nav['Administrasi'][] = ['admin.demo.index', 'i-users', 'Akun Demo', 'admin.demo.*', null];
    if (P::allows($u, 'backup', 'V')) $nav['Administrasi'][] = ['admin.backup.index', 'i-folder', 'Backup', 'admin.backup.*', null];
    $nav['Akun'][] = ['matrix', 'i-lock', 'Matriks Hak Akses', 'matrix', null];
    $nav['Akun'][] = ['profile.edit', 'i-user', 'Profil & Keamanan', 'profile.*', null];
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.theme-init')
    <meta name="theme-color" content="#0B2A5B">
    <title>@yield('title', 'E-Hibah') · E-Hibah Kepahiang</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-kepahiang.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
@include('partials.icons')
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <img src="{{ asset('img/logo-kepahiang.png') }}" alt="Lambang Kabupaten Kepahiang" width="30" height="38" style="object-fit:contain">
            <div><span>PEMERINTAH KABUPATEN</span><b>KEPAHIANG</b></div>
        </div>
        <nav class="nav">
            @foreach ($nav as $group => $items)
                <h6>{{ $group }}</h6>
                @foreach ($items as [$route, $icon, $label, $pattern])
                    <a href="{{ is_array($route) ? route($route[0], $route[1]) : route($route) }}" class="{{ $is($pattern) ? 'active' : '' }}" title="{{ $label }}">
                        <svg class="i"><use href="#{{ $icon }}"/></svg><span>{{ $label }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="side-art" aria-hidden="true"></div>
        <div class="side-foot"><em>Bersama Membangun Kepahiang</em></div>
    </aside>
    <div class="scrim"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn collapse-btn" data-collapse-nav aria-label="Ciutkan atau lebarkan menu" title="Ciutkan / lebarkan menu"><svg class="i i-lg"><use href="#i-menu"/></svg></button>
            <button class="icon-btn menu-btn" data-toggle-nav aria-label="Menu"><svg class="i i-lg"><use href="#i-menu"/></svg></button>
            <div class="title"><b>E-Hibah</b><small>@yield('heading', 'Kabupaten Kepahiang')</small></div>
            <div class="sp"></div>
            @include('partials.theme-switch')
            <a class="icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifikasi">
                <svg class="i i-lg"><use href="#i-bell"/></svg>@if ($unread)<span class="dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
            </a>
            <a class="user-chip" href="{{ route('profile.edit') }}">
                <span class="avatar">{{ $u->initials() }}</span>
                <div><small>Selamat Datang,</small><b>{{ \Illuminate\Support\Str::limit($u->name, 22) }}</b></div><svg class="i chev"><use href="#i-chev"/></svg>
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="icon-btn" title="Keluar" aria-label="Keluar"><svg class="i i-lg"><use href="#i-logout"/></svg></button>
            </form>
        </header>

        <main class="content">
            @include('partials.flash')
            @yield('content')
        </main>
        @include('partials.footer')
    </div>

    <nav class="bottom-nav" aria-label="Navigasi cepat">
        <a href="{{ route('dashboard') }}" class="{{ $is('dashboard') ? 'active' : '' }}"><svg class="i i-lg"><use href="#i-home"/></svg>Beranda</a>
        <a href="{{ route('applications.index') }}" class="{{ $is('applications.*') ? 'active' : '' }}"><svg class="i i-lg"><use href="#i-file"/></svg>Usulan</a>
        <a href="{{ route('notifications.index') }}" class="{{ $is('notifications.*') ? 'active' : '' }}"><svg class="i i-lg"><use href="#i-bell"/></svg>Notifikasi @if ($unread)<span class="dot">{{ $unread }}</span>@endif</a>
        <button type="button" data-toggle-nav><svg class="i i-lg"><use href="#i-menu"/></svg>Menu</button>
    </nav>
</div>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
