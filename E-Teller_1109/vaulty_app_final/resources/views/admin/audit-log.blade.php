@extends('layouts.admin')

@section('title', 'Log & Audit')

@section('content')
@php
    use App\Services\TransactionActivityLogger;

    $labelAksi = fn ($action) => [
        TransactionActivityLogger::ACTION_DIAJUKAN => 'Transaksi menunggu konfirmasi',
        TransactionActivityLogger::ACTION_BERHASIL => 'Transaksi berhasil',
        TransactionActivityLogger::ACTION_GAGAL => 'Transaksi gagal',
        'created' => 'Data dibuat',
        'updated' => 'Data diperbarui',
        'deleted' => 'Data dihapus',
    ][$action] ?? $action;

    $labelStatus = fn ($action) => match ($action) {
        TransactionActivityLogger::ACTION_GAGAL => 'Gagal',
        TransactionActivityLogger::ACTION_BERHASIL => 'Berhasil',
        default => 'Menunggu',
    };
    //Fungsi ini menentukan class CSS untuk warna label status.

    $badgeStatus = fn ($action) => match ($action) {
        TransactionActivityLogger::ACTION_GAGAL => 'badge-danger',
        TransactionActivityLogger::ACTION_BERHASIL => 'badge-ok',
        default => 'badge-warn',
    };
@endphp

<div class="actions section no-print" style="justify-content:flex-end">
    <a class="btn btn-primary btn-sm"
       href="{{ route('admin.audit-log.pdf', request()->only('jenis', 'q', 'from', 'to')) }}"
       target="_blank" rel="noopener"><x-icon name="file" /> Unduh PDF</a>
    <a class="btn btn-soft btn-sm"
       href="{{ route('admin.audit-log.excel', request()->only('jenis', 'q', 'from', 'to')) }}">
        <x-icon name="down" /> Unduh Excel
    </a>
</div>

<div class="grid-4">
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Total Log</span><span class="stat-icon"><x-icon name="list" /></span></div>
        <div><div class="stat-value">{{ number_format($ringkasan['total'], 0, ',', '.') }}</div><div class="stat-note">Seluruh aktivitas tercatat</div></div>
    </div>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Aktivitas Hari Ini</span><span class="stat-icon"><x-icon name="calendar" /></span></div>
        <div><div class="stat-value">{{ number_format($ringkasan['hari_ini'], 0, ',', '.') }}</div><div class="stat-note">Termasuk transaksi</div></div>
    </div>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Transaksi Berhasil</span><span class="stat-icon"><x-icon name="check" /></span></div>
        <div><div class="stat-value">{{ number_format($ringkasan['trx_berhasil'], 0, ',', '.') }}</div><div class="stat-note">Transaksi sukses</div></div>
    </div>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Transaksi Gagal</span><span class="stat-icon"><x-icon name="alert" /></span></div>
        <div><div class="stat-value" @if($ringkasan['trx_gagal'] > 0) style="color:var(--bad)" @endif>{{ number_format($ringkasan['trx_gagal'], 0, ',', '.') }}</div><div class="stat-note">Ditolak / tidak selesai</div></div>
    </div>
</div>

<p class="callout callout-info section">
    Log audit hanya dapat dilihat (tidak ada ubah/hapus). Halaman ini memuat <b>riwayat transaksi berhasil maupun gagal</b>
    beserta alasannya, ditambah aktivitas pengguna lain yang tercatat sistem.
</p>


<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Riwayat Transaksi (Berhasil &amp; Gagal)</h2>
            <p class="section-sub">
                {{ $riwayatTransaksi->total() }} aktivitas transaksi pada filter ini &middot;
                {{ $ringkasan['trx_berhasil'] }} berhasil &middot;
                {{ $ringkasan['trx_gagal'] }} gagal &middot;
                {{ $ringkasan['trx_menunggu'] }} menunggu konfirmasi.
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.audit-log') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
        <div class="field"><label for="aJenis">Jenis aktivitas</label>
            <select id="aJenis" name="jenis">
                <option value="semua" @selected($jenis === 'semua')>Semua aktivitas</option>
                <option value="transaksi" @selected($jenis === 'transaksi')>Semua transaksi</option>
                <option value="transaksi_berhasil" @selected($jenis === 'transaksi_berhasil')>Transaksi berhasil</option>
                <option value="transaksi_gagal" @selected($jenis === 'transaksi_gagal')>Transaksi gagal</option>
                <option value="transaksi_menunggu" @selected($jenis === 'transaksi_menunggu')>Transaksi menunggu</option>
                <option value="sistem" @selected($jenis === 'sistem')>Aktivitas sistem</option>
            </select>
        </div>
        <div class="field"><label for="aFrom">Dari tanggal</label><input id="aFrom" type="date" name="from" value="{{ $from }}"></div>
        <div class="field"><label for="aTo">Sampai tanggal</label><input id="aTo" type="date" name="to" value="{{ $to }}"></div>
        <div class="field"><label for="aQ">Cari</label><input id="aQ" type="search" name="q" value="{{ $q }}" placeholder="Nama, aksi, atau keterangan"></div>
        <div class="field"><a href="{{ route('admin.audit-log') }}" class="btn btn-soft btn-sm">Reset</a></div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Waktu</th><th>Petugas</th><th>Aktivitas</th><th>Keterangan / Alasan</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($riwayatTransaksi as $log)
                <tr>
                    <td class="nw">
                        <span class="name">{{ optional($log->created_at)->format('d/m/Y') }}</span>
                        <span class="sub mono">{{ optional($log->created_at)->format('H:i:s') }}</span>
                    </td>
                    <td>
                        <div class="name">{{ $log->user->name ?? 'Sistem' }}</div>
                        <div class="sub">{{ $log->user->username ?? '-' }}</div>
                    </td>
                    <td><span class="badge badge-soft">{{ $labelAksi($log->action) }}</span></td>
                    <td class="sub" style="min-width:280px;white-space:normal">{{ $log->description ?: '-' }}</td>
                    <td class="nw"><span class="badge {{ $badgeStatus($log->action) }}">{{ $labelStatus($log->action) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-cell">Belum ada riwayat transaksi yang tercatat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($riwayatTransaksi->hasPages())<x-pager :paginator="$riwayatTransaksi" />@endif
</section>

@unless($jenis === 'sistem')
<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Transaksi Tercatat</h2>
            <p class="section-sub">
                {{ $transaksiTercatat->total() }} transaksi pada arsip transaksi (termasuk data lama)
                &middot; total arsip {{ $ringkasan['trx_tercatat'] }} transaksi.
            </p>
        </div>
        <span class="badge badge-soft">Hanya baca</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Waktu</th><th>Referensi</th><th>Nasabah</th><th>Teller</th><th>Jenis</th><th class="num">Nominal</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
            @forelse($transaksiTercatat as $tx)
                @php
                    $keterangan = match ($tx->status) {
                        'approved', 'success' => 'Berhasil',
                        'rejected', 'void' => 'Gagal',
                        'corrected' => 'Dikoreksi',
                        'pending' => 'Menunggu',
                        default => ucfirst((string) $tx->status),
                    };

                    $badgeTrx = match ($tx->status) {
                        'approved', 'success' => 'badge-ok',
                        'rejected', 'void' => 'badge-danger',
                        'pending' => 'badge-warn',
                        default => 'badge-soft',
                    };
                @endphp
                <tr>
                    <td class="nw">{{ optional($tx->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="nw">{{ $tx->reference_number ?: $tx->transaction_code }}</td>
                    <td>
                        <div class="name">{{ $tx->account->user->name ?? '-' }}</div>
                        <div class="sub">{{ $tx->account->account_number ?? '-' }}</div>
                    </td>
                    <td>{{ $tx->teller->name ?? '-' }}</td>
                    <td><span class="badge badge-soft">{{ $tx->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                    <td class="amount num">Rp {{ number_format($tx->amount, 0, ',', '.') }}</td>
                    <td>
                        <span class="badge {{ $badgeTrx }}">{{ $keterangan }}</span>
                        @if($tx->rejected_reason)<div class="sub" style="white-space:normal">{{ $tx->rejected_reason }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Belum ada transaksi pada arsip.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transaksiTercatat->hasPages())<x-pager :paginator="$transaksiTercatat" />@endif
</section>
@endunless

<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Riwayat Aktivitas Sistem</h2>
            <p class="section-sub">{{ $logs->total() }} aktivitas pengguna dan proses lain yang tercatat sistem.</p>
        </div>
        <span class="badge badge-soft">Hanya baca</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Peran</th>
                    <th>Aktivitas</th>
                    <th>Modul</th>
                    <th>IP address</th>
                    <th class="num">Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $peran = $log->user->role ?? null;
                @endphp
                <tr>
                    <td class="nw">
                        <span class="name">{{ optional($log->created_at)->format('d/m/Y') }}</span>
                        <span class="sub mono">{{ optional($log->created_at)->format('H:i:s') }}</span>
                    </td>
                    <td>
                        <div class="name">{{ $log->user->name ?? 'Sistem' }}</div>
                        <div class="sub">{{ $log->user->username ?? 'sistem' }}</div>
                    </td>
                    <td class="nw">
                        <span class="badge {{ match($peran) {
                            'admin' => 'badge-soft',
                            'teller' => 'badge-ok',
                            'supervisor' => 'badge-soft',
                            'customer' => 'badge-outline',
                            default => 'badge-outline',
                        } }}">
                            {{ ['admin' => 'Administrator', 'supervisor' => 'Supervisor', 'teller' => 'Teller', 'customer' => 'Nasabah'][$peran] ?? 'Sistem' }}
                        </span>
                    </td>
                    <td style="min-width:220px">
                        <span class="name">{{ $labelAksi($log->action ?? '-') }}</span>
                        @if($log->description)<span class="sub">{{ $log->description }}</span>@endif
                    </td>
                    <td class="nw">
                        @if($log->module)
                            <span class="badge badge-soft">{{ $log->module }}</span>
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                    <td class="nw"><span class="mono" style="color:var(--muted)">{{ $log->ip_address ?? '-' }}</span></td>
                    <td class="nw">
                        @if($log->status === 'success')
                            <span class="badge badge-ok">Berhasil</span>
                        @elseif($log->status === 'failed')
                            <span class="badge badge-danger">Gagal</span>
                        @else
                            <span class="badge badge-outline">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Belum ada aktivitas yang tercatat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<x-pager :paginator="$logs" />@endif
</section>
@endsection

