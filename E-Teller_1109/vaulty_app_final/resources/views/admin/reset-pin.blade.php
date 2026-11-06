@extends('layouts.admin')

@section('title', 'Pengajuan Reset PIN')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pengajuan Reset PIN</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Reset PIN</b></div>
        </div>
    </div>

    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Menunggu persetujuan</div>
                <div class="section-sub">Periksa bukti verifikasi teller sebelum menyetujui reset.</div>
            </div>
            <span class="badge {{ $pending->count() ? 'badge-warn' : 'badge-ok' }}">{{ $pending->count() }} pengajuan</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Nasabah</th><th>Teller</th><th>Verifikasi &amp; alasan</th><th>Berlaku sampai</th><th class="num">Aksi</th></tr></thead>
                <tbody>
                    @forelse($pending as $item)
                        <tr>
                            <td class="nw">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                            <td><div class="name">{{ $item->account?->user?->name ?? '-' }}</div><div class="sub">{{ $item->account?->account_number ?? '-' }}</div></td>
                            <td>{{ $item->requester?->name ?? '-' }}</td>
                            <td style="min-width:240px;white-space:normal"><div>{{ $item->reason }}</div><div class="sub">{{ $item->verification_note }}</div></td>
                            <td class="nw">{{ $item->expires_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions end">
                                    <form method="POST" action="{{ route('admin.reset-pin.approve', $item->id) }}" onsubmit="return confirm('Setujui reset PIN menjadi 123456 sementara?')">
                                        @csrf
                                        <button class="btn btn-primary btn-sm" type="submit">Setujui</button>
                                    </form>
                                    <button class="btn btn-danger btn-sm" type="button" data-reject="{{ route('admin.reset-pin.reject', $item->id) }}" data-reject-title="Tolak reset PIN {{ $item->account?->user?->name ?? '' }}">Tolak</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">Tidak ada pengajuan reset PIN.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card section">
        <div class="section-head"><div><div class="section-title">Riwayat pengajuan</div><div class="section-sub">Pengajuan yang sudah disetujui, ditolak, atau kedaluwarsa.</div></div></div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Nasabah</th><th>Diajukan oleh</th><th>Status</th><th>Diproses oleh</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @forelse($history as $item)
                        <tr>
                            <td class="nw">{{ $item->created_at->format('d/m/Y H:i') }}</td>
                            <td><div class="name">{{ $item->account?->user?->name ?? '-' }}</div><div class="sub">{{ $item->account?->account_number ?? '-' }}</div></td>
                            <td>{{ $item->requester?->name ?? '-' }}</td>
                            <td><span class="badge {{ $item->status === 'approved' ? 'badge-ok' : ($item->status === 'rejected' ? 'badge-danger' : 'badge-warn') }}">{{ $item->statusLabel() }}</span></td>
                            <td>{{ $item->reviewer?->name ?? '-' }}</td>
                            <td class="sub">{{ $item->review_note ?: $item->reason }}</td>
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