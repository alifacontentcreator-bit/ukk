<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name')) &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="app-shell et-shell">
<div class="app-topbar et-topbar">
    <div class="topbar-left">
        <button class="menu-toggle" type="button" data-sidebar-toggle aria-label="Perkecil atau perbesar menu" aria-expanded="true" title="Perkecil / perbesar menu">
            <x-icon name="panel-left" />
        </button>
        <a class="brand et-brand" href="{{ url('/') }}">
            <span class="brand-mark">
                <img src="{{ asset('images/logo-smkn1-bantul.png') }}" alt="Logo SMK Negeri 1 Bantul">
            </span>
            <span class="brand-text">
                <strong class="brand-name">{{ config('app.name') }}</strong>
                <span class="brand-sub">SMK Negeri 1 Bantul</span>
            </span>
        </a>
        <span class="brand-divider"></span>
        <span class="brand-tagline">Sistem Tabungan Siswa</span>
    </div>

    <div class="topbar-right">
        <div class="et-clock" data-et-clock>
            <x-icon name="clock" />
            <span data-clock-date>{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            <b data-clock-time>{{ now()->format('H:i:s') }} WIB</b>
        </div>
        @hasSection('topbar-actions')
            <div class="topbar-notif">@yield('topbar-actions')</div>
        @endif
        <x-user-menu />
    </div>
</div>

<aside class="app-sidebar et-sidebar" id="appSidebar" aria-label="Menu utama">
    <nav class="sidebar-nav et-nav">@yield('nav')</nav>
    <form class="sidebar-foot" action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit" title="Keluar dari aplikasi"><x-icon name="logout" /><span>Keluar</span></button>
    </form>
</aside>
<div class="app-scrim et-scrim" data-sidebar-close></div>

<main class="app-main et-main">
    <div class="app-content et-content">
        @if(session('success') || session('error') || (isset($errors) && $errors->any()))
            <div class="alert-stack">
                @if(session('success'))
                    <div class="alert alert-success"><x-icon name="check" /><span>{{ session('success') }}</span></div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger"><x-icon name="alert" /><span>{{ session('error') }}</span></div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <x-icon name="alert" />
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
            </div>
        @endif

        @unless($__env->hasSection('no-page-head'))
            <div class="page-head et-page-head">
                <div>
                    <h1>@if($__env->hasSection('page-title'))@yield('page-title')@elseif($__env->hasSection('page-heading'))@yield('page-heading')@else Dasbor @endif</h1>
                    @hasSection('page-subtitle')
                        <p>@yield('page-subtitle')</p>
                    @else
                        <div class="crumb et-crumb">
                            <span>@yield('role')</span><i>/</i>
                            <b>@if($__env->hasSection('page-title'))@yield('page-title')@elseif($__env->hasSection('page-heading'))@yield('page-heading')@else Dasbor @endif</b>
                        </div>
                    @endif
                </div>
                <div class="page-actions">@yield('page-actions')</div>
            </div>
        @endunless

        @yield('content')
    </div>

    <footer class="app-foot">
        {{ config('app.name') }} &middot; SMK Negeri 1 Bantul &mdash; sistem ini hanya untuk pengguna berwenang.
    </footer>
</main>

@stack('scripts')
</body>
</html>
