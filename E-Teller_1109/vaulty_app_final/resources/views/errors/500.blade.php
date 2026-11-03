@extends('layouts.auth')

@section('title', 'Terjadi Gangguan')
@section('heading', 'Terjadi gangguan pada sistem')
@section('subheading', 'Permintaan Anda belum dapat diproses saat ini.')
@section('visual-title', 'Sistem sedang mengalami gangguan.')
@section('visual-text', 'Coba beberapa saat lagi. Bila berulang, laporkan ke petugas agar segera diperiksa.')

@section('content')
    <div class="err-code">500</div>
    <p class="auth-desc">
        Data transaksi yang sudah tersimpan tetap utuh. Hindari mengulang pengiriman berulang kali
        agar tidak terjadi pencatatan ganda.
    </p>

    <div class="err-actions">
        <button class="btn btn-primary btn-block" type="button" onclick="location.reload()">Muat Ulang Halaman</button>
        <a class="btn btn-soft btn-block" href="{{ url('/') }}">Kembali ke Aplikasi</a>
    </div>

    <p class="err-note">Kode galat <code>500</code> &middot; Hubungi admin bila gangguan berlanjut.</p>
@endsection
