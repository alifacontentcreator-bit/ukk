{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman reset sandi, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.auth')

@php
    // Data email memang sudah dikirim controller; tampilkan tersamar, bukan utuh.
    $resetEmail = $email ?? '';
    if ($resetEmail !== '' && str_contains($resetEmail, '@')) {
        [$localPart, $domainPart] = explode('@', $resetEmail, 2);
        $visiblePart = mb_substr($localPart, 0, 2);
        $resetEmail = $visiblePart . str_repeat('•', max(3, mb_strlen($localPart) - mb_strlen($visiblePart))) . '@' . $domainPart;
    }
    $resetSubheading = $resetEmail !== ''
        ? 'Tautan reset untuk ' . $resetEmail . ' hanya dapat dipakai satu kali.'
        : 'Tautan reset ini hanya dapat dipakai satu kali.';
@endphp

@section('title', 'Atur ulang sandi')
@section('head-icon', 'key')
@section('heading', 'Buat sandi baru')
@section('subheading', $resetSubheading)

@section('content')
    <form class="auth-form" action="{{ route('password.reset', $token) }}" method="POST">
        @csrf
        <div class="field">
            <label for="password">Sandi baru</label>
            <div class="field-lead field-icon">
                <x-icon name="lock" />
                <input id="password" name="password" type="password" minlength="8"
                       autocomplete="new-password" placeholder="Minimal 8 karakter" required>
                <button class="toggle-pass" type="button" data-toggle-pass="password"
                        aria-label="Tampilkan sandi" aria-pressed="false">
                    <x-icon name="eye" class="ico-show" />
                    <x-icon name="eye-off" class="ico-off" />
                </button>
            </div>
            @error('password')<p class="form-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Konfirmasi sandi</label>
            <div class="field-lead field-icon">
                <x-icon name="lock" />
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8"
                       autocomplete="new-password" placeholder="Ketik ulang sandi baru" required>
                <button class="toggle-pass" type="button" data-toggle-pass="password_confirmation"
                        aria-label="Tampilkan sandi" aria-pressed="false">
                    <x-icon name="eye" class="ico-show" />
                    <x-icon name="eye-off" class="ico-off" />
                </button>
            </div>
            @error('password_confirmation')<p class="form-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-info">
            <x-icon name="info" />
            <span>Gunakan minimal 8 karakter dan jangan bagikan sandi kepada siapa pun, termasuk petugas.</span>
        </div>

        <button class="auth-submit" type="submit">
            <span>Simpan sandi baru</span>
            <x-icon name="arrow" />
        </button>
    </form>

    <div class="auth-back auth-back--split">
        <a class="auth-link" href="{{ route('password.forgot') }}">
            <x-icon name="arrow-left" />Ajukan pemulihan baru
        </a>
        <a class="auth-link" href="{{ route('login') }}">Kembali ke halaman masuk</a>
    </div>
@endsection
