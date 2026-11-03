@extends('layouts.supervisor')
@section('title', 'Validasi Koreksi')
@section('page-title', 'Validasi Koreksi')
@section('page-subtitle', 'Periksa pengajuan koreksi teller. Saldo nasabah baru disesuaikan setelah Anda menyetujui.')

@section('content')
<div class="seg" style="margin-bottom:20px">
    @foreach(['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'] as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }}@if($key !== 'all') ({{ $counts[$key] ?? 0 }})@endif</a>
    @endforeach
</div>

<section class="card">
    <form method="GET" action="{{ route('supervisor.koreksi') }}" class="toolbar" data-auto-filter>
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="field"><label for="kTeller">Teller</label>
            <select id="kTeller" name="teller_id">
                <option value="">Semua teller</option>
                @foreach($tellers as $t)<option value="{{ $t->id }}" @selected((string) request('teller_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="field" style="grid-column:span 2"><label for="kQ">Cari</label><input id="kQ" type="search" name="q" value="{{ request('q') }}" placeholder="Nama nasabah, no. rekening, kode transaksi, atau alasan"></div>
        <div class="field"><a href="{{ route('supervisor.koreksi', ['status' => $status]) }}" class="btn btn-soft">Bersihkan filter</a></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Transaksi asal</th><th>Nasabah</th><th>Teller</th><th>Jenis</th><th class="num">Nominal</th><th>Kode &amp; alasan</th><th class="num">Aksi</th></tr></thead>
            <tbody>
            @forelse($corrections as $c)
                <tr>
                    <td class="nw">{{ optional($c->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="nw">{{ $c->transaction->transaction_code ?? '-' }}</td>
                    <td><div class="name">{{ $c->transaction->account->user->name ?? '-' }}</div><div class="sub">{{ $c->transaction->account->account_number ?? '-' }}</div></td>
                    <td>{{ $c->teller->name ?? '-' }}</td>
                    <td><span class="badge {{ $c->type === 'deposit_correction' ? 'badge-ok' : 'badge-warn' }}">{{ $c->type === 'deposit_correction' ? 'Tambah saldo' : 'Kurangi saldo' }}</span></td>
                    <td class="amount num">Rp {{ number_format($c->corrected_amount, 0, ',', '.') }}</td>
                    <td style="min-width:220px;white-space:normal"><div class="name">{{ $c->correction_code }}</div><div class="sub">{{ $c->reason }}</div></td>
                    <td>
                        @if($c->status === 'pending')
                            <div class="actions end">
                                <form action="{{ route('supervisor.koreksi.approve', $c->id) }}" method="POST" onsubmit="return confirm('Setujui koreksi ini? Saldo nasabah akan langsung disesuaikan.')">@csrf<button class="btn btn-primary btn-sm" type="submit">Setujui</button></form>
                                <button class="btn btn-danger btn-sm" type="button" data-reject="{{ route('supervisor.koreksi.reject', $c->id) }}" data-reject-title="Tolak koreksi {{ $c->correction_code }}">Tolak</button>
                            </div>
                        @else
                            <div class="num"><span class="badge {{ $c->status === 'approved' ? 'badge-ok' : 'badge-danger' }}">{{ $c->status === 'approved' ? 'Disetujui' : 'Ditolak' }}</span><div class="sub">{{ $c->supervisor->name ?? '-' }}</div></div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">Tidak ada pengajuan koreksi yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($corrections->hasPages())<x-pager :paginator="$corrections" />@endif
</section>

<x-reject-dialog />
@endsection