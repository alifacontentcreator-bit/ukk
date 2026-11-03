@extends('layouts.supervisor')
@section('title', 'Validasi Reset PIN')
@section('page-title', 'Validasi Reset PIN')
@section('page-subtitle', 'Setelah disetujui, nasabah dapat membuat PIN baru sendiri melalui aplikasi.')

@section('content')
<section class="card">
    <div class="section-head">
        <div><h2 class="section-title">Pengajuan Menunggu</h2><p class="section-sub">Pastikan alasan masuk akal dan identitas sudah diperiksa teller. Pembuat pengajuan tidak dapat menyetujui sendiri.</p></div>
        <span class="badge {{ $pending->count() ? 'badge-warn' : 'badge-ok' }}">{{ $pending->count() }} menunggu</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Nasabah</th><th>Teller</th><th>Identitas &amp; Alasan</th><th>Berlaku sampai</th><th class="num">Aksi</th></tr></thead>
            <tbody>
            @forelse($pending as $r)
                <tr>
                    <td class="nw">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td><div class="name">{{ $r->account->user->name ?? '-' }}</div><div class="sub">{{ $r->account->account_number ?? '-' }}</div></td>
                    <td>{{ $r->requester->name ?? '-' }}</td>
                    <td style="min-width:260px;white-space:normal"><div class="name">{{ $r->reason }}</div><div class="sub">{{ $r->verification_note }}</div></td>
                    <td class="nw">{{ $r->expires_at->format('d/m/Y H:i') }}</td>
                    <td>
                        <div class="actions end">
                            <form action="{{ route('supervisor.reset-pin.approve', $r->id) }}" method="POST" onsubmit="return confirm('Setujui reset PIN? Nasabah akan diminta membuat PIN baru di aplikasi.')">@csrf<button class="btn btn-primary btn-sm" type="submit">Setujui</button></form>
                            <button class="btn btn-danger btn-sm" type="button" data-reject="{{ route('supervisor.reset-pin.reject', $r->id) }}" data-reject-title="Tolak reset PIN {{ $r->account->user->name ?? '' }}">Tolak</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-cell">Tidak ada pengajuan reset PIN yang menunggu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Riwayat Pengajuan</h2><p class="section-sub">Pengajuan yang sudah diproses, termasuk reset darurat oleh Administrator.</p></div></div>
    <form method="GET" action="{{ route('supervisor.reset-pin') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
        <div class="field"><label for="rStatus">Status</label>
            <select id="rStatus" name="status">
                <option value="">Semua</option>
                <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                <option value="expired" @selected(request('status') === 'expired')>Kedaluwarsa</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
            </select>
        </div>
        <div class="field" style="grid-column:span 2"><label for="rQ">Cari nasabah</label><input id="rQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nama atau nomor rekening"></div>
        <div class="field"><a href="{{ route('supervisor.reset-pin') }}" class="btn btn-soft">Bersihkan filter</a></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Nasabah</th><th>Diajukan oleh</th><th>Status</th><th>Diproses oleh</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($history as $r)
                <tr>
                    <td class="nw">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td><div class="name">{{ $r->account->user->name ?? '-' }}</div><div class="sub">{{ $r->account->account_number ?? '-' }}</div></td>
                    <td>{{ $r->requester->name ?? '-' }}</td>
                    <td><span class="badge {{ $r->status === 'approved' ? 'badge-ok' : ($r->status === 'rejected' ? 'badge-danger' : 'badge-warn') }}">{{ $r->statusLabel() }}</span></td>
                    <td>{{ $r->reviewer->name ?? '-' }}</td>
                    <td style="min-width:220px;white-space:normal" class="sub">{{ $r->review_note ?: $r->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-cell">Belum ada riwayat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($history->hasPages())<x-pager :paginator="$history" />@endif
</section>

<x-reject-dialog />
@endsection