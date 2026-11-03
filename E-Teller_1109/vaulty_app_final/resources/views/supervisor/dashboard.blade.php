@extends('layouts.supervisor')

@section('title', 'Dasbor Supervisor')
@section('page-title', 'Ringkasan Operasional')
@section('page-subtitle', 'Periksa laporan akhir teller dan pantau penutupan kas harian.')

@section('content')
@php
    $berhasilHariIni = \App\Models\Transaction::whereDate('created_at', today())->whereIn('status', ['approved', 'success']);
    $trxHariIni = (clone $berhasilHariIni)->count();
    $setoranHariIni = (clone $berhasilHariIni)->where('type', 'deposit')->sum('amount');
    $penarikanHariIni = (clone $berhasilHariIni)->where('type', 'withdrawal')->sum('amount');

    $laporanMasuk = \App\Models\DailyReport::where('status', 'submitted')->count();
    $koreksiPending = \App\Models\TransactionCorrection::where('status', 'pending')->count();
    $resetPending = \App\Models\CredentialResetRequest::open()->where('type', 'pin')->count();
    $siapDipindah = \App\Models\DailyReport::where('status', 'approved')->where('transfer_status', 'pending')->count();
    $transaksiLama = \App\Models\Transaction::where('status', 'pending')->count();

    $laporanHariIni = \App\Models\DailyReport::with('teller')->whereDate('report_date', today())->get()->map(function ($r) {
        $r->selisih_hitung = ($r->saldo_awal + ($r->pecahan_100k * 100000) + ($r->pecahan_50k * 50000) + ($r->pecahan_20k * 20000)) - ($r->saldo_awal + $r->expected_cash);
        return $r;
    });
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp

@if($transaksiLama > 0)
    <div class="alert alert-info">
        <x-icon name="info" />
        <span>Ada {{ $transaksiLama }} transaksi dari alur lama yang masih berstatus menunggu. <a href="{{ route('supervisor.validasi') }}" style="font-weight:700;text-decoration:underline">Selesaikan di sini</a>.</span>
    </div>
@endif

<div class="grid-4">
    <a href="{{ route('supervisor.laporan') }}" class="card stat">
        <div class="stat-top"><span class="stat-label">Laporan akhir menunggu</span><span class="stat-icon"><x-icon name="file" /></span></div>
        <div><div class="stat-value">{{ $laporanMasuk }}</div><div class="stat-note">{{ $siapDipindah }} kas siap ke brankas</div></div>
    </a>
    <a href="{{ route('supervisor.koreksi') }}" class="card stat">
        <div class="stat-top"><span class="stat-label">Koreksi menunggu</span><span class="stat-icon"><x-icon name="flag" /></span></div>
        <div><div class="stat-value">{{ $koreksiPending }}</div><div class="stat-note">Pengajuan dari teller</div></div>
    </a>
    <a href="{{ route('supervisor.reset-pin') }}" class="card stat">
        <div class="stat-top"><span class="stat-label">Reset PIN menunggu</span><span class="stat-icon"><x-icon name="refresh" /></span></div>
        <div><div class="stat-value">{{ $resetPending }}</div><div class="stat-note">Pengajuan dari teller</div></div>
    </a>
    <a href="{{ route('supervisor.jurnal') }}" class="card stat">
        <div class="stat-top"><span class="stat-label">Transaksi hari ini</span><span class="stat-icon"><x-icon name="swap" /></span></div>
        <div><div class="stat-value">{{ $trxHariIni }}</div><div class="stat-note">{{ $rp($setoranHariIni) }} masuk &middot; {{ $rp($penarikanHariIni) }} keluar</div></div>
    </a>
</div>

<div class="section split">
    <section class="card">
        <div class="section-head">
            <div><h2 class="section-title">Laporan Hari Ini</h2><p class="section-sub">Status penutupan kas tiap teller.</p></div>
            <a href="{{ route('supervisor.rekonsiliasi') }}" class="more">Rekonsiliasi kas</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Teller</th><th>Laporan</th><th>Selisih</th><th>Kas</th></tr></thead>
                <tbody>
                @forelse($laporanHariIni as $r)
                    <tr>
                        <td class="name">{{ $r->teller->name ?? '-' }}</td>
                        <td><span class="badge {{ $r->status === 'approved' ? 'badge-ok' : ($r->status === 'submitted' ? 'badge-warn' : 'badge-soft') }}">{{ ['approved' => 'Disetujui', 'submitted' => 'Terkirim', 'draft' => 'Draf', 'rejected' => 'Ditolak'][$r->status] ?? ucfirst($r->status) }}</span></td>
                        <td>@if(abs($r->selisih_hitung) < 0.01)<span class="badge badge-ok">Klop</span>@else<span class="badge badge-danger">{{ $rp(abs($r->selisih_hitung)) }}</span>@endif</td>
                        <td><span class="badge {{ $r->transfer_status === 'approved' ? 'badge-ok' : 'badge-warn' }}">{{ $r->transfer_status === 'approved' ? 'Di brankas' : 'Di laci' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">Belum ada laporan hari ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Perlu Tindakan</h2><p class="section-sub">Antrean yang menunggu keputusan Anda.</p></div></div>
        <div class="list">
            @foreach([
                ['Laporan akhir teller', $laporanMasuk, 'supervisor.laporan'],
                ['Kas siap dipindahkan', $siapDipindah, 'supervisor.laporan'],
                ['Pengajuan koreksi', $koreksiPending, 'supervisor.koreksi'],
                ['Pengajuan reset PIN', $resetPending, 'supervisor.reset-pin'],
            ] as [$label, $count, $route])
                <a href="{{ route($route) }}" class="list-row">
                    <span class="name">{{ $label }}</span>
                    <span class="badge {{ $count > 0 ? 'badge-warn' : 'badge-ok' }}">{{ $count > 0 ? $count . ' menunggu' : 'Selesai' }}</span>
                </a>
            @endforeach
        </div>
    </section>
</div>
@endsection