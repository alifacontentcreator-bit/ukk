{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman 404, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.auth')

@section('title', 'Halaman Tidak Ditemukan')
@section('heading', 'Halaman tidak ditemukan')
@section('subheading', 'Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.')
@section('visual-title', 'Halaman yang Anda tuju tidak tersedia.')
@section('visual-text', 'Periksa kembali tautan atau alamat yang Anda masukkan, lalu ulangi dari menu aplikasi.')

@section('content')
    <div class="err-code">404</div>
    <p class="auth-desc">
        Tautan mungkin salah ketik, sudah kedaluwarsa, atau datanya telah dihapus.
        Seluruh catatan tabungan tetap aman dan tidak terpengaruh.
    </p>

    <div class="err-actions">
        <a class="btn btn-primary btn-block" href="{{ url('/') }}">Kembali ke Aplikasi</a>
        <button class="btn btn-soft btn-block" type="button" onclick="history.back()">Halaman Sebelumnya</button>
    </div>

    <p class="err-note">Kode galat <code>404</code> &middot; Bila masalah berlanjut, hubungi petugas teller.</p>
@endsection
