@extends('layouts.supervisor')
@section('title', 'Jurnal Audit')
@section('page-title', 'Jurnal Audit')
@section('page-subtitle', 'Telusuri semua transaksi teller. Supervisor hanya dapat melihat, tidak mengubah.')

@section('content')
<div class="grid-4">
@foreach([['Total transaksi', $stats['total'], 'Sesuai filter saat ini', 'swap'], ['Berhasil', $stats['approved'], 'Termasuk koreksi', 'check'], ['Menunggu (alur lama)', $stats['pending'], 'Sisa transaksi lama', 'clock'], ['Ditolak', $stats['rejected'], 'Transaksi ditolak', 'x']] as [$label, $value, $note, $icon])
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">{{ $label }}</span><span class="stat-icon"><x-icon :name="$icon" /></span></div>
        <div><div class="stat-value">{{ $value }}</div><div class="stat-note">{{ $note }}</div></div>
    </div>
@endforeach
</div>

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
        <div class="field"><label for="jStatus">Status</label>
            <select id="jStatus" name="status">
                <option value="">Semua</option>
                <option value="approved" @selected(request('status') === 'approved')>Berhasil</option>
                <option value="success" @selected(request('status') === 'success')>Koreksi</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                <option value="pending" @selected(request('status') === 'pending')>Menunggu (alur lama)</option>
            </select>
        </div>
        <div class="field"><label for="jQ">Cari</label><input id="jQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nasabah, rekening, atau referensi"></div>
        <div class="field"><a href="{{ route('supervisor.jurnal') }}" class="btn btn-soft">Bersihkan filter</a></div>
    </form>
</section>

<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Jurnal Transaksi</h2><p class="section-sub">{{ $transaksi->total() }} transaksi ditemukan.</p></div></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Referensi</th><th>Nasabah</th><th>Teller</th><th>Jenis</th><th class="num">Nominal</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($transaksi as $tx)
                <tr>
                    <td class="nw">{{ optional($tx->approved_at ?? $tx->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="nw">{{ $tx->reference_number ?: $tx->transaction_code }}</td>
                    <td><div class="name">{{ $tx->account->user->name ?? '-' }}</div><div class="sub">{{ $tx->account->account_number ?? '-' }}</div></td>
                    <td>{{ $tx->teller->name ?? '-' }}</td>
                    <td><span class="badge {{ $tx->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $tx->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                    <td class="amount num">Rp {{ number_format($tx->amount, 0, ',', '.') }}</td>
                    <td><span class="badge {{ $tx->status === 'pending' ? 'badge-warn' : ($tx->status === 'rejected' ? 'badge-danger' : 'badge-ok') }}">{{ ['pending' => 'Menunggu', 'approved' => 'Berhasil', 'rejected' => 'Ditolak', 'success' => 'Koreksi', 'corrected' => 'Dikoreksi'][$tx->status] ?? ucfirst($tx->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Tidak ada data yang cocok dengan filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transaksi->hasPages())<x-pager :paginator="$transaksi" />@endif
</section>
@endsection