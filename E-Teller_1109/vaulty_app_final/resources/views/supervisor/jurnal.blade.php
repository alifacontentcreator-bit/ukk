@extends('layouts.supervisor')

{{--
    HALAMAN INI UNTUK: Jurnal Audit Supervisor.
    - Kartu ringkasan: total / berhasil / gagal / menunggu.
    - Tabel "Jurnal Transaksi"      : semua transaksi (berhasil & gagal) + alasannya.
    - Tabel "Aktivitas Transaksi"   : jejak setiap percobaan transaksi (menunggu/berhasil/gagal).
    - Hanya bisa dilihat (read-only).
--}}
@section('title', 'Jurnal Audit')
@section('page-title', 'Jurnal Audit')
@section('page-subtitle', 'Telusuri seluruh aktivitas transaksi teller, berhasil maupun gagal, beserta keterangannya. Supervisor hanya dapat melihat — data tidak pernah diubah atau dihapus.')

@php
    $labelStatus = fn ($s) => [
        'pending' => 'Menunggu', 'approved' => 'Berhasil', 'berhasil' => 'Berhasil',
        'success' => 'Berhasil', 'rejected' => 'Gagal', 'failed' => 'Gagal',
        'void' => 'Dibatalkan', 'corrected' => 'Dikoreksi',
    ][$s] ?? ($s ? ucfirst((string) $s) : '-');

    $badgeStatus = fn ($s) => in_array($s, ['approved', 'success', 'berhasil'], true)
        ? 'badge-ok'
        : (in_array($s, ['rejected', 'void', 'failed'], true) ? 'badge-danger' : 'badge-warn');

    $labelAksi = fn ($action) => [
        'transaksi.diajukan' => 'Permintaan transaksi',
        'transaksi.berhasil' => 'Transaksi berhasil',
        'transaksi.gagal' => 'Transaksi gagal',
    ][$action] ?? $action;
@endphp

@section('content')
<div class="grid-4">
@foreach([
    ['Total transaksi', $stats['total'], 'Sesuai filter saat ini', 'swap'],
    ['Berhasil', $stats['berhasil'], 'Transaksi sukses', 'check'],
    ['Gagal', $stats['gagal'], 'Ditolak / dibatalkan', 'x'],
    ['Menunggu', $stats['menunggu'], 'Belum dikonfirmasi', 'clock'],
] as [$label, $value, $note, $icon])
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">{{ $label }}</span><span class="stat-icon"><x-icon :name="$icon" /></span></div>
        <div><div class="stat-value">{{ $value }}</div><div class="stat-note">{{ $note }}</div></div>
    </div>
@endforeach
</div>

<p class="callout callout-info section">
    Jurnal audit ini <b>hanya dapat dilihat</b>. Tidak ada tombol ubah maupun hapus, dan seluruh aktivitas transaksi
    (berhasil maupun gagal) tersimpan permanen beserta keterangannya.
</p>

<section class="card section">
    <form method="GET" action="{{ route('supervisor.jurnal') }}" class="toolbar" data-auto-filter>
        <div class="field"><label for="jFrom">Dari tanggal</label><input id="jFrom" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="field"><label for="jTo">Sampai tanggal</label><input id="jTo" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="field"><label for="jTeller">Teller</label>
            <select id="jTeller" name="teller_id">
                <option value="">Semua teller</option>
                @foreach($tellers as $t)<option value="{{ $t->id }}" @selected((string) request('teller_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label for="jType">Jenis</label>
            <select id="jType" name="type">
                <option value="">Semua</option>
                <option value="deposit" @selected(request('type') === 'deposit')>Setoran</option>
                <option value="withdrawal" @selected(request('type') === 'withdrawal')>Penarikan</option>
            </select>
        </div>
        <div class="field"><label for="jStatus">Keterangan</label>
            <select id="jStatus" name="status">
                <option value="">Semua</option>
                <option value="berhasil" @selected(request('status') === 'berhasil')>Berhasil</option>
                <option value="gagal" @selected(request('status') === 'gagal')>Gagal</option>
                <option value="menunggu" @selected(request('status') === 'menunggu')>Menunggu</option>
                <option value="corrected" @selected(request('status') === 'corrected')>Dikoreksi</option>
            </select>
        </div>
        <div class="field"><label for="jQ">Cari</label><input id="jQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nasabah, rekening, atau referensi"></div>
        <div class="field"><a href="{{ route('supervisor.jurnal') }}" class="btn btn-soft">Bersihkan filter</a></div>
    </form>
</section>

<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Jurnal Transaksi</h2><p class="section-sub">{{ $transaksi->total() }} transaksi ditemukan &middot; arsip sejak {{ $awalTransaksi ? \Carbon\Carbon::parse($awalTransaksi)->format('d/m/Y') : '-' }} &middot; tanpa filter tanggal, seluruh riwayat ditampilkan.</p></div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Referensi</th><th>Nasabah</th><th>Teller</th><th>Jenis</th><th class="num">Nominal</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($transaksi as $tx)
                <tr>
                    <td class="nw">{{ optional($tx->approved_at ?? $tx->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="nw">{{ $tx->reference_number ?: $tx->transaction_code }}</td>
                    <td><div class="name">{{ $tx->account->user->name ?? '-' }}</div><div class="sub">{{ $tx->account->account_number ?? '-' }}</div></td>
                    <td>{{ $tx->teller->name ?? '-' }}</td>
                    <td><span class="badge {{ $tx->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $tx->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                    <td class="amount num">Rp {{ number_format($tx->amount, 0, ',', '.') }}</td>
                    <td>
                        <span class="badge {{ $badgeStatus($tx->status) }}">{{ $labelStatus($tx->status) }}</span>
                        @if($tx->rejected_reason)<div class="sub">{{ $tx->rejected_reason }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Tidak ada data yang cocok dengan filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transaksi->hasPages())<x-pager :paginator="$transaksi" />@endif
</section>

<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Aktivitas Transaksi (Termasuk yang Gagal)</h2>
            <p class="section-sub">
                {{ $ringkasAktivitas['total'] }} aktivitas tercatat &middot;
                {{ $ringkasAktivitas['berhasil'] }} berhasil &middot;
                {{ $ringkasAktivitas['gagal'] }} gagal &middot;
                {{ $ringkasAktivitas['menunggu'] }} menunggu konfirmasi &middot;
                setiap percobaan transaksi dicatat tanpa terkecuali.
            </p>
        </div>
        <span class="badge badge-soft">Hanya baca</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Teller</th><th>Aktivitas</th><th>Keterangan</th><th>Keterangan Proses</th></tr></thead>
            <tbody>
            @forelse($aktivitas as $log)
                <tr>
                    <td class="nw">{{ optional($log->created_at)->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->user->name ?? 'Sistem' }}</td>
                    <td><span class="badge badge-soft">{{ $labelAksi($log->action) }}</span></td>
                    <td class="sub" style="min-width:260px;white-space:normal">{{ $log->description ?: '-' }}</td>
                    <td>
                        @if($log->action === \App\Services\TransactionActivityLogger::ACTION_GAGAL)
                            <span class="badge badge-danger">Gagal</span>
                        @elseif($log->action === \App\Services\TransactionActivityLogger::ACTION_BERHASIL)
                            <span class="badge badge-ok">Berhasil</span>
                        @else
                            <span class="badge badge-warn">Menunggu</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-cell">Belum ada aktivitas transaksi yang tercatat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($aktivitas->hasPages())<x-pager :paginator="$aktivitas" />@endif
</section>
@endsection