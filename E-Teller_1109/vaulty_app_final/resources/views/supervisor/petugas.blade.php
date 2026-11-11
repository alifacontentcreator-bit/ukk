{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman petugas, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.supervisor')

{{--
    HALAMAN INI UNTUK: Data Petugas (daftar teller).
    - Filter cari: NAMA dan KELAS (tidak ada filter status akun).
    - Tabel hanya menampilkan Nama dan Kelas.
--}}
@section('title', 'Data Petugas')
@section('page-title', 'Data Petugas')
@section('page-subtitle', 'Daftar teller beserta kelasnya. Cari berdasarkan nama atau kelas.')

@section('content')
<section class="card">
    <div class="section-head"><div><h2 class="section-title">Daftar Petugas</h2><p class="section-sub">{{ $petugas->total() }} teller terdaftar.</p></div></div>
    <form method="GET" action="{{ route('supervisor.petugas') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
        <div class="field"><label for="pNama">Cari nama</label><input id="pNama" type="search" name="nama" value="{{ request('nama') }}" placeholder="Nama petugas"></div>
        <div class="field"><label for="pKelas">Cari kelas</label><input id="pKelas" type="search" name="kelas" value="{{ request('kelas') }}" placeholder="Contoh: XI AKL 2"></div>
        <div class="field"><a href="{{ route('supervisor.petugas') }}" class="btn btn-soft btn-sm">Reset</a></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nama</th><th>Kelas</th></tr></thead>
            <tbody>
            @forelse($petugas as $petugasKe)
                <tr>
                    <td class="name">{{ $petugasKe->name }}</td>
                    <td>{{ $petugasKe->kelas ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-cell">Tidak ada petugas yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($petugas->hasPages())<x-pager :paginator="$petugas" />@endif
</section>
@endsection
