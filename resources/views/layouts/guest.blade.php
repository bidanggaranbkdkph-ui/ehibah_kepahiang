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
<body class="guest">
@include('partials.icons')
<header class="pub-top">
    <div class="in">
        <a class="brand" href="{{ route('home') }}" style="text-decoration:none">
            <img src="{{ asset('img/logo-kepahiang.png') }}" alt="Lambang Kabupaten Kepahiang" width="38" height="48" style="object-fit:contain">
            <div><b>e-HIBAH</b><span>KABUPATEN KEPAHIANG</span></div>
        </a>
        <nav class="pub-nav" aria-label="Menu utama">
            <a href="{{ route('home') }}" data-spy="top" class="{{ request()->routeIs('home') ? 'on' : '' }}">Beranda</a>
            <a href="{{ route('home') }}#dasar-hukum" data-spy="dasar-hukum">Dasar Hukum</a>
            <a href="{{ route('home') }}#alur" data-spy="alur">Alur Pengajuan</a>
            <a href="{{ route('home') }}#syarat" data-spy="syarat">Syarat &amp; Ketentuan</a>
            <a href="{{ route('home') }}#transparansi" data-spy="transparansi">Transparansi</a>
            <a href="{{ route('public.status') }}" class="{{ request()->routeIs('public.status') ? 'on' : '' }}">Cek Status</a>
        </nav>
        <div class="pub-actions">
            @include('partials.theme-switch')
            @auth
                <a class="cta" href="{{ route('dashboard') }}">DASHBOARD</a>
            @else
                <a class="cta" href="{{ route('login') }}">LOGIN / DAFTAR</a>
            @endauth
        </div>
    </div>
</header>
@yield('hero')
<main class="{{ trim($__env->yieldContent('wrap')) ?: 'auth-box' }}">
    @include('partials.flash')
    @yield('content')
</main>
@include('partials.footer')
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
