<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Masuk') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="auth-shell">
    {{-- Kolom kiri: foto gedung bleed penuh + lapisan hijau army. Kiri atas sengaja dikosongkan. --}}
    <aside class="auth-visual">
        <img src="{{ asset('images/gedung-smkn1-bantul.jpg') }}" alt="Gedung SMK Negeri 1 Bantul">
        {{-- Dekorasi halus: garis lingkaran konsentris putih di pojok kanan atas foto. --}}
        <svg class="auth-visual-rings" viewBox="0 0 300 300" aria-hidden="true" focusable="false">
            <circle cx="300" cy="0" r="48" />
            <circle cx="300" cy="0" r="96" />
            <circle cx="300" cy="0" r="144" />
            <circle cx="300" cy="0" r="192" />
        </svg>
        <div class="auth-visual-inner">
            <div class="auth-visual-body">
                <span class="auth-chip">
                    <x-icon name="shield" />
                    <span>Sistem Tabungan Siswa</span>
                </span>
                <h2>@yield('visual-title', 'Tabungan siswa yang tertib, tercatat, dan mudah diperiksa.')</h2>
                <p>@yield('visual-text', 'Setoran, penarikan, koreksi, dan rekonsiliasi kas harian dalam satu alur kerja yang rapi.')</p>
                {{-- Tiga poin kecil: hanya fitur yang memang ada di aplikasi. --}}
                <ul class="auth-features">
                    <li class="auth-feature">
                        <span class="auth-feature-ico"><x-icon name="coins" /></span>
                        <span>Setoran &amp; penarikan</span>
                    </li>
                    <li class="auth-feature">
                        <span class="auth-feature-ico"><x-icon name="sliders" /></span>
                        <span>Koreksi terkontrol</span>
                    </li>
                    <li class="auth-feature">
                        <span class="auth-feature-ico"><x-icon name="refresh" /></span>
                        <span>Rekonsiliasi kas harian</span>
                    </li>
                </ul>
            </div>
            <div class="auth-address">
                <x-icon name="building" />
                <span>SMK Negeri 1 Bantul &middot; Bantul, DI Yogyakarta</span>
            </div>
        </div>
    </aside>

    <main class="auth-panel">
        <div class="auth-form-wrap">
            {{-- Blok identitas: tampil di semua ukuran layar, termasuk saat foto disembunyikan. --}}
            <div class="auth-identity">
                <span class="auth-identity-mark">
                    <img src="{{ asset('images/logo-smkn1-bantul.png') }}" alt="Logo SMK Negeri 1 Bantul">
                </span>
                <span>
                    <strong class="auth-identity-name">{{ config('app.name') }}</strong>
                    <span class="auth-identity-sub">Sistem Tabungan Siswa</span>
                </span>
            </div>

            @php($headIcon = trim($__env->yieldContent('head-icon')))
            @if($headIcon !== '')
                <span class="auth-head-ico"><x-icon :name="$headIcon" /></span>
            @endif

            @hasSection('heading')
                <h1 class="auth-title">@yield('heading')</h1>
            @endif
            @hasSection('subheading')
                <p class="auth-desc @yield('desc-class')">@yield('subheading')</p>
            @endif

            @yield('content')

            <p class="auth-foot">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </main>

    @stack('scripts')
</body>
</html>

