@extends('layouts.app')

@section('title', 'Bukti Transaksi Digital')
@section('page-title', 'Bukti Transaksi')
@section('page-subtitle', 'Tunjukkan bukti ini bila diperlukan.')

@section('page-actions')
    <button class="btn btn-primary btn-sm no-print" type="button" onclick="window.print()"><x-icon name="printer" /> Cetak</button>
    <a class="btn btn-soft btn-sm no-print" href="{{ route('customer.dashboard') }}"><x-icon name="arrow-left" /> Dasbor</a>
@endsection

@section('content')
    <div class="receipt">
        <div class="receipt-head">
            <div class="receipt-title">Bukti Transaksi</div>
            <div class="receipt-sub">{{ config('app.name') }} &middot; SMK Negeri 1 Bantul</div>
            <div class="receipt-sub" style="margin-top:6px"><span class="badge badge-ok">Transaksi berhasil</span></div>
        </div>

        <div class="receipt-body">
            <div class="receipt-row"><span>Nasabah</span><b>{{ auth()->user()->name }}</b></div>
            <div class="receipt-row"><span>Rekening</span><b class="mono">{{ $transaction->account->account_number }}</b></div>
            <div class="receipt-row"><span>Referensi</span><b class="mono">{{ $transaction->reference_number }}</b></div>
            <div class="receipt-row"><span>Jenis</span><b>{{ $transaction->type === 'deposit' ? 'Setoran Tunai' : 'Penarikan Tunai' }}</b></div>
            <div class="receipt-row"><span>Waktu</span><b>{{ $transaction->approved_at?->format('d/m/Y H:i:s') ?? '-' }}</b></div>
        </div>

        <div class="receipt-total">
            <span>Nominal</span>
            <b>Rp {{ number_format($transaction->amount, 0, ',', '.') }}</b>
        </div>

        <div class="receipt-body" style="border-bottom:0;padding-bottom:0">
            <div class="receipt-row">
                <span>Saldo setelah transaksi</span>
                <b>Rp {{ number_format(data_get($transaction->data_after, 'balance', 0), 0, ',', '.') }}</b>
            </div>
        </div>

        <div class="receipt-foot">
            Dicetak {{ now()->format('d/m/Y H:i') }} &middot; dokumen ini dihasilkan otomatis oleh sistem.
        </div>
    </div>
@endsection
