@extends('layouts.teller')

@section('title', 'Struk Transaksi')
@section('page-title', 'Struk Transaksi')
@section('page-subtitle', 'Pratinjau struk sebelum dicetak.')

@section('page-actions')
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><x-icon name="printer" /> Cetak</button>
    <a class="btn btn-soft btn-sm" href="{{ route('teller.transaksi.create') }}"><x-icon name="arrow-left" /> Transaksi</a>
@endsection

@section('content')
    <div class="receipt">
        <div class="receipt-head">
            <div class="receipt-title">Struk Transaksi</div>
            <div class="receipt-sub">{{ config('app.name') }} &middot; SMK Negeri 1 Bantul</div>
            <div class="receipt-sub">Ref: {{ $transaction->reference_number }}</div>
        </div>

        <div class="receipt-body">
            <div class="receipt-row"><span>Nama nasabah</span><b>{{ $transaction->account->user->name }}</b></div>
            <div class="receipt-row"><span>No. rekening</span><b class="mono">{{ $transaction->account->account_number }}</b></div>
            <div class="receipt-row"><span>Jenis</span><b>{{ strtoupper($transaction->type) }}</b></div>
            <div class="receipt-row">
                <span>Status</span>
                <b style="color:{{ $transaction->status === 'pending' ? 'var(--warn)' : (in_array($transaction->status, ['approved', 'success'], true) ? 'var(--ok)' : 'var(--bad)') }}">
                    {{ ['pending' => 'Menunggu (alur lama)', 'approved' => 'Berhasil', 'rejected' => 'Ditolak', 'success' => 'Berhasil'][$transaction->status] ?? ucfirst($transaction->status) }}
                </b>
            </div>
        </div>

        <div class="receipt-total">
            <span>Total</span>
            <b>Rp {{ number_format($transaction->amount, 0, ',', '.') }}</b>
        </div>

        <div class="receipt-foot">
            @if($transaction->status === 'pending')
                Transaksi tercatat dan menunggu proses dari alur lama.
            @else
                Terima kasih atas transaksi Anda.
            @endif
        </div>
    </div>
@endsection
