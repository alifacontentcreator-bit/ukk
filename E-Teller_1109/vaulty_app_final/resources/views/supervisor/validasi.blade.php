@extends('layouts.supervisor')
@section('title', 'Transaksi Lama Menunggu')
@section('page-title', 'Transaksi Lama Menunggu')
@section('page-subtitle', 'Sisa transaksi dari alur sebelumnya. Transaksi baru langsung dicatat teller dan diperiksa lewat laporan akhir.')

@section('content')
<section class="card">
    <div class="section-head">
        <div><h2 class="section-title">Antrean Sisa Transaksi</h2><p class="section-sub">Selesaikan satu per satu. Setelah kosong, halaman ini tidak perlu dibuka lagi.</p></div>
        <span class="badge {{ $transactions->count() ? 'badge-warn' : 'badge-ok' }}">{{ $transactions->count() }} menunggu</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Referensi</th><th>Nasabah</th><th>Teller</th><th>Jenis</th><th class="num">Nominal</th><th class="num">Aksi</th></tr></thead>
            <tbody>
            @forelse($transactions as $t)
                <tr>
                    <td class="nw">{{ optional($t->created_at)->format('d/m/Y H:i') }}</td>
                    <td class="nw">{{ $t->reference_number }}</td>
                    <td><div class="name">{{ $t->account->user->name ?? '-' }}</div><div class="sub">{{ $t->account->account_number ?? '-' }}</div></td>
                    <td>{{ $t->teller->name ?? '-' }}</td>
                    <td><span class="badge {{ $t->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $t->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                    <td class="amount num">Rp {{ number_format($t->amount, 0, ',', '.') }}</td>
                    <td>
                        <div class="actions end">
                            <form action="{{ route('supervisor.validasi.approve', $t->id) }}" method="POST" onsubmit="return confirm('Setujui transaksi ini?')">@csrf<button class="btn btn-primary btn-sm" type="submit">Setujui</button></form>
                            <button class="btn btn-danger btn-sm" type="button" data-reject="{{ route('supervisor.validasi.reject', $t->id) }}" data-reject-title="Tolak transaksi {{ $t->reference_number }}">Tolak</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Tidak ada transaksi lama yang menunggu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<x-reject-dialog />
@endsection