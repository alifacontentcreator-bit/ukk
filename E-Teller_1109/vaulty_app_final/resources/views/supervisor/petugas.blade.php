@extends('layouts.supervisor')

@section('title', 'Data Petugas')
@section('page-title', 'Data Petugas')
@section('page-subtitle', 'Daftar teller beserta kelas dan status akunnya.')

@section('content')
<section class="card">
    <div class="section-head"><div><h2 class="section-title">Daftar Petugas</h2><p class="section-sub">{{ $petugas->total() }} teller terdaftar.</p></div></div>
    <form method="GET" action="{{ route('supervisor.petugas') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
        <div class="field" style="flex:1 1 220px;max-width:none"><label for="pQ">Cari</label><input id="pQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nama atau kelas"></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nama</th><th>Kelas</th><th>Akun</th></tr></thead>
            <tbody>
            @forelse($petugas as $p)
                <tr>
                    <td class="name">{{ $p->name }}</td>
                    <td>{{ $p->kelas ?: '-' }}</td>
                    <td><span class="badge {{ $p->status === 'active' ? 'badge-ok' : 'badge-danger' }}">{{ $p->status === 'active' ? 'Aktif' : 'Diblokir' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty-cell">Tidak ada petugas yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($petugas->hasPages())<x-pager :paginator="$petugas" />@endif
</section>
@endsection
