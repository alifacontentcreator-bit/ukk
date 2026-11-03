@extends('layouts.supervisor')

@section('title', 'Data Petugas')
@section('page-title', 'Data Petugas')
@section('page-subtitle', 'Daftar teller beserta kelas dan nomor WhatsApp yang bisa dihubungi, dapat diimpor dan diunduh sebagai Excel.')
@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('supervisor.petugas.export', request()->query()) }}"><x-icon name="down" /> Unduh Excel</a>
@endsection

@section('content')
<div class="split">
    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Daftar Petugas</h2><p class="section-sub">{{ $petugas->total() }} teller &middot; {{ $tanpaWa }} belum punya nomor WA.</p></div></div>
        <form method="GET" action="{{ route('supervisor.petugas') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
            <div class="field" style="grid-column:span 2"><label for="pQ">Cari</label><input id="pQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nama atau kelas"></div>
            <div class="field"><label for="pWa">Nomor WA</label>
                <select id="pWa" name="wa"><option value="">Semua</option><option value="belum" @selected(request('wa') === 'belum')>Belum ada</option></select>
            </div>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Nama</th><th>Kelas</th><th>No. WA yang bisa dihubungi</th><th>Akun</th></tr></thead>
                <tbody>
                @forelse($petugas as $p)
                    @php $wa = $sheet->waLink($p->no_telp); @endphp
                    <tr>
                        <td class="name">{{ $p->name }}</td>
                        <td>{{ $p->kelas ?: '-' }}</td>
                        <td class="nw">@if($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="more">{{ $p->no_telp }}</a>@else<span class="muted">{{ $p->no_telp ?: 'Belum diisi' }}</span>@endif</td>
                        <td><span class="badge {{ $p->status === 'active' ? 'badge-ok' : 'badge-danger' }}">{{ $p->status === 'active' ? 'Aktif' : 'Diblokir' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">Tidak ada petugas yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($petugas->hasPages())<x-pager :paginator="$petugas" />@endif
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Impor dari Excel</h2><p class="section-sub">Memperbarui nomor WA teller dari berkas absensi.</p></div></div>
        <form method="POST" action="{{ route('supervisor.petugas.impor') }}" enctype="multipart/form-data" class="card-body stack">
            @csrf
            <div class="callout callout-info">
                Kolom yang dibaca: <b>Nama</b>, <b>Kelas</b>, <b>No. WA</b>. Petugas dicocokkan lewat nama (dan kelas). Baris yang tidak pasti dilewati, tidak ditebak.
            </div>
            <div class="field"><label for="pFile">File (.xlsx, .xls, .csv, maks. 2 MB)</label><input id="pFile" type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
            <div class="actions">
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="down" /> Impor</button>
                <a class="btn btn-soft btn-sm" href="{{ route('supervisor.petugas.export', ['templat' => 1]) }}">Unduh templat</a>
            </div>
        </form>
    </section>
</div>

@if(session('import_skipped') && count(session('import_skipped')))
<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Baris yang Dilewati</h2><p class="section-sub">Perbaiki di Excel lalu impor ulang, atau ubah nomornya langsung di Manajemen Pengguna.</p></div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Baris</th><th>Nama</th><th>Alasan</th></tr></thead>
            <tbody>@foreach(session('import_skipped') as $s)<tr><td class="nw">{{ $s['row'] }}</td><td>{{ $s['nama'] }}</td><td>{{ $s['alasan'] }}</td></tr>@endforeach</tbody>
        </table>
    </div>
</section>
@endif
@endsection