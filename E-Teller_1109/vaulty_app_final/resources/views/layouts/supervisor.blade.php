@extends('layouts.shell')
@section('role', 'Supervisor')

@section('nav')
            <div class="nav-group">Utama</div>
            <a class="nav-item {{ request()->routeIs('supervisor.dashboard') ? 'active' : '' }}" href="{{ route('supervisor.dashboard') }}" title="Dasbor"><x-icon name="home" /><span>Dasbor</span></a>
            <div class="nav-group">Validasi</div>
            <a class="nav-item {{ request()->routeIs('supervisor.koreksi*') ? 'active' : '' }}" href="{{ route('supervisor.koreksi') }}" title="Validasi Koreksi"><x-icon name="flag" /><span>Validasi Koreksi</span></a>
            <a class="nav-item {{ request()->routeIs('supervisor.reset-pin*') ? 'active' : '' }}" href="{{ route('supervisor.reset-pin') }}" title="Validasi Reset PIN"><x-icon name="key" /><span>Validasi Reset PIN</span></a>
            <div class="nav-group">Petugas</div>
            <a class="nav-item {{ request()->routeIs('supervisor.absensi*') ? 'active' : '' }}" href="{{ route('supervisor.absensi') }}" title="Absensi Petugas"><x-icon name="check" /><span>Absensi Petugas</span></a>
            <a class="nav-item {{ request()->routeIs('supervisor.jadwal*') ? 'active' : '' }}" href="{{ route('supervisor.jadwal') }}" title="Jadwal Jaga"><x-icon name="calendar" /><span>Jadwal Jaga</span></a>
            <a class="nav-item {{ request()->routeIs('supervisor.petugas*') ? 'active' : '' }}" href="{{ route('supervisor.petugas') }}" title="Data Petugas"><x-icon name="users" /><span>Data Petugas</span></a>
            <div class="nav-group">Kas &amp; Laporan</div>
            <a class="nav-item {{ request()->routeIs('supervisor.laporan*') ? 'active' : '' }}" href="{{ route('supervisor.laporan') }}" title="Laporan Akhir"><x-icon name="chart" /><span>Laporan Akhir</span></a>
            <a class="nav-item {{ request()->routeIs('supervisor.rekonsiliasi*') ? 'active' : '' }}" href="{{ route('supervisor.rekonsiliasi') }}" title="Rekonsiliasi Kas"><x-icon name="swap" /><span>Rekonsiliasi Kas</span></a>
            <a class="nav-item {{ request()->routeIs('supervisor.jurnal*') ? 'active' : '' }}" href="{{ route('supervisor.jurnal') }}" title="Jurnal Audit"><x-icon name="book" /><span>Jurnal Audit</span></a>
@endsection