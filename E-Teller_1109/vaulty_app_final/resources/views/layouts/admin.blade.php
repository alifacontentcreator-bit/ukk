@extends('layouts.shell')
@section('role', 'Administrator')
@section('no-page-head', '1')
@section('nav')
            <div class="nav-group">Utama</div>
            <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" title="Dasbor"><x-icon name="home" /><span>Dasbor</span></a>
            <div class="nav-group">Data Master</div>
            <a class="nav-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}" href="{{ route('admin.users') }}" title="Manajemen Pengguna"><x-icon name="users" /><span>Manajemen Pengguna</span></a>
            <a class="nav-item {{ request()->routeIs('admin.rekening*') ? 'active' : '' }}" href="{{ route('admin.rekening') }}" title="Master Rekening"><x-icon name="card" /><span>Master Rekening</span></a>
            <div class="nav-group">Sistem</div>
            <a class="nav-item {{ request()->routeIs('admin.konfigurasi*') ? 'active' : '' }}" href="{{ route('admin.konfigurasi') }}" title="Konfigurasi Sistem"><x-icon name="sliders" /><span>Konfigurasi Sistem</span></a>
            <a class="nav-item {{ request()->routeIs('admin.audit-log') ? 'active' : '' }}" href="{{ route('admin.audit-log') }}" title="Log &amp; Audit"><x-icon name="shield" /><span>Log &amp; Audit</span></a>
            <a class="nav-item {{ request()->routeIs('admin.pemulihan-sandi*') ? 'active' : '' }}" href="{{ route('admin.pemulihan-sandi') }}" title="Atur Ulang Sandi"><x-icon name="lock" /><span>Atur Ulang Sandi</span></a>
            <div class="nav-group">Akuntansi</div>
            <a class="nav-item {{ request()->routeIs('admin.jurnal*') ? 'active' : '' }}" href="{{ route('admin.jurnal') }}" title="Jurnal Akuntansi"><x-icon name="book" /><span>Jurnal Akuntansi</span></a>
@endsection