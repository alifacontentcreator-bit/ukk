<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Teller Tabungan Siswa &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        .splash {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(900px 420px at 12% -10%, #E6EDDA 0%, transparent 60%),
                var(--sage);
        }
        .splash-card {
            width: min(460px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(24, 52, 32, .07);
            padding: 26px 24px 22px;
            text-align: center;
        }
        .splash-logo {
            width: 58px;
            height: 58px;
            margin: 0 auto 12px;
            object-fit: contain;
        }
        .splash-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            background: var(--sage);
            color: var(--green-dark);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .splash-title {
            margin: 12px 0 6px;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.01em;
            color: var(--green-dark);
        }
        .splash-sub {
            margin: 0 auto;
            max-width: 330px;
            font-size: 13px;
            line-height: 1.6;
            color: var(--muted);
        }
        .splash-foot {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            font-size: 11.5px;
            color: var(--faint);
        }
        .splash-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 18px;
        }
    </style>
</head>
<body class="splash">
    <main class="splash-card">
        <img class="splash-logo" src="{{ asset('images/logo-smkn1-bantul.png') }}" alt="Logo SMK Negeri 1 Bantul">
        <span class="splash-eyebrow">LKM Mitra Siswa Abadi</span>
        <h1 class="splash-title">Aplikasi Teller Tabungan Siswa</h1>
        <p class="splash-sub">
            Setoran, penarikan, koreksi, dan rekonsiliasi kas harian dalam satu alur kerja
            yang tertib dan mudah diperiksa.
        </p>

        <div class="splash-actions">
            @auth
                <a class="btn btn-primary btn-sm" href="{{ url('/dashboard') }}">Buka Dasbor</a>
            @else
                <a class="btn btn-primary btn-sm" href="{{ route('login') }}">Masuk ke Aplikasi</a>
            @endauth
            <a class="btn btn-soft btn-sm" href="{{ route('password.forgot') }}">Lupa sandi</a>
        </div>

        <div class="splash-foot">
            SMK Negeri 1 Bantul &middot; Bantul, DI Yogyakarta
        </div>
    </main>
</body>
</html>
