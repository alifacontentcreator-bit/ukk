@extends('layouts.auth')

@section('title', 'Masuk')
@section('heading', 'Selamat Datang Kembali')
@section('subheading', 'Masuk dengan nama pengguna dan sandi dari Administrator.')

@section('content')
    {{-- Satu blok pesan saja di atas formulir: tidak menggandakan pesan yang sama. --}}
    @if(session('success'))
        <div class="auth-alert is-success" role="status">
            <x-icon name="check" /><span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="auth-alert is-error" role="alert">
            <x-icon name="alert" />
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="auth-form" action="{{ route('login.process') }}" method="POST">
        @csrf
        <div class="field">
            <label for="username">Nama pengguna</label>
            <div class="field-lead">
                <x-icon name="user" />
                <input id="username" name="username" type="text" value="{{ old('username') }}"
                       placeholder="NIS atau NIP" autocomplete="username" required autofocus>
            </div>
        </div>
        <div class="field">
            <label for="password">Sandi</label>
            <div class="field-lead field-icon">
                <x-icon name="lock" />
                <input id="password" name="password" type="password" placeholder="Masukkan sandi"
                       autocomplete="current-password" required>
                <button class="toggle-pass" type="button" data-toggle-pass="password"
                        aria-label="Tampilkan sandi" aria-pressed="false">
                    <x-icon name="eye" class="ico-show" />
                    <x-icon name="eye-off" class="ico-off" />
                </button>
            </div>
        </div>
        <div class="auth-row">
            <a class="auth-link" href="{{ route('password.forgot') }}">Lupa sandi?</a>
        </div>
        <button class="auth-submit" type="submit">
            <span>Masuk</span><x-icon name="arrow" />
        </button>
    </form>

    <p class="auth-note">Belum punya akun? Hubungi teller atau Administrator sekolah.</p>
    <p class="auth-locked"><x-icon name="lock" /><span>Hanya untuk pengguna terdaftar.</span></p>
@endsection

