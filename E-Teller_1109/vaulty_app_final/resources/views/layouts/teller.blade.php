{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman teller, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.shell')
@section('role', 'Teller')

@section('topbar-actions')
    <x-report-notification-bell />
@endsection

@section('nav')
            <div class="nav-group">Utama</div>
            <a class="nav-item {{ request()->routeIs('teller.dashboard') ? 'active' : '' }}" href="{{ route('teller.dashboard') }}" title="Dashboard"><x-icon name="home" /><span>Dashboard</span></a>
            <div class="nav-group">Layanan</div>
            <a class="nav-item {{ request()->routeIs('teller.transaksi*') ? 'active' : '' }}" href="{{ route('teller.transaksi.create') }}" title="Transaksi Nasabah"><x-icon name="plus-circle" /><span>Transaksi Nasabah</span></a>
            <a class="nav-item {{ request()->routeIs('teller.mutasi*') ? 'active' : '' }}" href="{{ route('teller.mutasi') }}" title="Cetak Mutasi"><x-icon name="receipt" /><span>Cetak Mutasi</span></a>
            <div class="nav-group">Jaga</div>
            <a class="nav-item {{ request()->routeIs('teller.absensi*') ? 'active' : '' }}" href="{{ route('teller.absensi') }}" title="Absensi Jaga"><x-icon name="check" /><span>Absensi Jaga</span></a>
            <div class="nav-group">Riwayat &amp; Laporan</div>
            <a class="nav-item {{ request()->routeIs('teller.koreksi*') ? 'active' : '' }}" href="{{ route('teller.koreksi') }}" title="Riwayat &amp; Koreksi"><x-icon name="history" /><span>Riwayat &amp; Koreksi</span></a>
            <a class="nav-item {{ request()->routeIs('teller.laporan*') ? 'active' : '' }}" href="{{ route('teller.laporan') }}" title="Laporan Harian"><x-icon name="file" /><span>Laporan Harian</span></a>
@endsection