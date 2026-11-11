@extends('layouts.shell')
@section('role', 'Nasabah')

@section('nav')
            <div class="nav-group">Menu</div>
            <a class="nav-item {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}" href="{{ route('customer.dashboard') }}" title="Dasbor"><x-icon name="home" /><span>Dasbor</span></a>
            <a class="nav-item {{ request()->routeIs('customer.saldo') ? 'active' : '' }}" href="{{ route('customer.saldo') }}" title="Saldo"><x-icon name="wallet" /><span>Saldo</span></a>
            <a class="nav-item {{ request()->routeIs('customer.history') ? 'active' : '' }}" href="{{ route('customer.history') }}" title="Riwayat Transaksi"><x-icon name="history" /><span>Riwayat Transaksi</span></a>
            <a class="nav-item {{ request()->routeIs('customer.mutasi*') ? 'active' : '' }}" href="{{ route('customer.mutasi') }}" title="Mutasi Rekening"><x-icon name="receipt" /><span>Mutasi Rekening</span></a>
            <a class="nav-item {{ request()->routeIs('customer.notifications') ? 'active' : '' }}" href="{{ route('customer.notifications') }}" title="Notifikasi"><x-icon name="bell" /><span>Notifikasi</span></a>
            <a class="nav-item {{ request()->routeIs('customer.qr-data') ? 'active' : '' }}" href="{{ route('customer.qr-data') }}" title="QR Data Nasabah"><x-icon name="qr" /><span>QR Data Nasabah</span></a>
@endsection