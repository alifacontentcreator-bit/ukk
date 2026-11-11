{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman 403, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.auth')

@section('title', 'Akses Ditolak')
@section('heading', 'Akses ditolak')
@section('subheading', 'Peran akun Anda tidak memiliki izin untuk halaman ini.')
@section('visual-title', 'Halaman ini hanya untuk peran tertentu.')
@section('visual-text', 'Gunakan akun dengan hak akses yang sesuai, atau kembali ke dasbor peran Anda.')

@section('content')
    <div class="err-code">403</div>
    <p class="auth-desc">
        Halaman yang Anda tuju dibatasi untuk peran lain. Bila Anda merasa berhak mengaksesnya,
        mintalah admin menyesuaikan hak akses akun Anda.
    </p>

    <div class="err-actions">
        <a class="btn btn-primary btn-block" href="{{ url('/') }}">Kembali ke Aplikasi</a>
        <button class="btn btn-soft btn-block" type="button" onclick="history.back()">Halaman Sebelumnya</button>
    </div>

    <p class="err-note">Kode galat <code>403</code> &middot; Hubungi admin bila hak akses perlu diubah.</p>
@endsection
