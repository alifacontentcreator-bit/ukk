@extends('layouts.auth')

@section('title', 'Sesi Berakhir')
@section('heading', 'Sesi Anda telah berakhir')
@section('subheading', 'Halaman kedaluwarsa karena terlalu lama tidak ada aktivitas.')
@section('visual-title', 'Demi keamanan, sesi ditutup otomatis.')
@section('visual-text', 'Masuk kembali untuk melanjutkan pekerjaan. Data yang sudah tersimpan tidak hilang.')

@section('content')
    <div class="alert alert-info">
        <div>
            <b>Halaman kedaluwarsa.</b>
            Formulir yang Anda kirim tidak diproses agar tidak terjadi data ganda.
        </div>
    </div>

    <div class="err-code">419</div>
    <p class="auth-desc">
        Silakan masuk kembali, lalu ulangi langkah terakhir Anda. Bila sedang mengisi formulir panjang,
        salin dulu datanya sebelum keluar.
    </p>

    <div class="err-actions">
        <a class="btn btn-primary btn-block" href="{{ url('/') }}">Masuk Kembali</a>
    </div>

    <p class="err-note">Kode galat <code>419</code> &middot; Sesi kedaluwarsa demi keamanan akun.</p>
@endsection
