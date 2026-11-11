{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman cetak slip, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.teller')

@section('title', 'Struk Transaksi')
@section('page-title', 'Struk Transaksi')
@section('page-subtitle', 'Pratinjau slip. Cetak slip hanya tersedia dalam format PDF.')

@section('page-actions')
    <a class="btn btn-primary btn-sm" href="{{ route('teller.transaksi.cetak-pdf', $transaction) }}" target="_blank" rel="noopener"><x-icon name="file" /> Unduh PDF</a>
    <a class="btn btn-soft btn-sm"
       href="{{ route('teller.mutasi', ['account_number' => $transaction->account->account_number, 'from' => ($transaction->approved_at ?? $transaction->created_at)->copy()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]) }}">
        <x-icon name="receipt" /> Cetak Mutasi
    </a>
    <a class="btn btn-soft btn-sm" href="{{ route('teller.transaksi.create') }}"><x-icon name="arrow-left" /> Transaksi</a>
@endsection

@section('content')
    @php
        $at = $transaction->approved_at ?? $transaction->created_at;
        $isDeposit = $transaction->type === 'deposit';
        $jenis = $isDeposit ? 'Setoran Tunai' : 'Penarikan Tunai';
        $tellerName = $transaction->teller?->name ?? '-';
    @endphp

    <style>
        .slip-watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            user-select: none;
            z-index: 0;
        }
        .slip-watermark span {
            transform: rotate(-28deg);
            font-size: 54px;
            font-weight: 800;
            letter-spacing: 7px;
            color: rgba(30, 41, 27, 0.08);
            white-space: nowrap;
        }
        .slip-content { position: relative; z-index: 1; }
        .slip-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
            border-bottom: 1px dashed #e3e8e2;
            font-size: 12px;
        }
        .slip-row:last-child { border-bottom: 0; }
        .slip-row span { color: #5a625d; }
        .slip-row b { text-align: right; color: #1e291b; }
        .slip-total {
            margin: 12px 0;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f0f2ec;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
        }
        @media print {
            body * { visibility: hidden; }
            .receipt, .receipt * { visibility: visible; }
            .receipt {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                max-width: 360px;
                margin: 0 auto;
                box-shadow: none !important;
                border: 0 !important;
            }
            .slip-watermark span {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>

    <div class="receipt" style="position:relative;overflow:hidden;max-width:360px;margin:0 auto;border:1px dashed #cbd5c8;border-radius:12px;background:#fff;padding:18px 18px 14px">
        <div class="slip-watermark"><span>BANK MINI</span></div>

        <div class="slip-content">
            <div style="text-align:center;padding-bottom:10px;border-bottom:2px solid #1e291b">
                <div style="font-size:15px;font-weight:800;letter-spacing:.4px;color:#1e291b">LKM MITRA SISWA ABADI</div>
                <div style="font-size:10px;color:#5a625d;margin-top:2px">BANK MINI &middot; SMK NEGERI 1 BANTUL</div>
                <div style="margin-top:10px;display:inline-block;padding:4px 12px;border-radius:999px;background:#1e291b;color:#fff;font-size:11px;font-weight:700;letter-spacing:.4px">
                    SLIP {{ strtoupper($jenis) }}
                </div>
            </div>

            <div style="margin-top:10px">
                <div class="slip-row"><span>No. Referensi</span><b class="mono">{{ $transaction->reference_number ?: $transaction->transaction_code }}</b></div>
                <div class="slip-row"><span>No. Rekening</span><b class="mono">{{ $transaction->account->account_number }}</b></div>
                <div class="slip-row"><span>Nama</span><b>{{ $transaction->account->user->name }}</b></div>
                <div class="slip-row"><span>Tanggal</span><b>{{ $at?->format('d/m/Y') }}</b></div>
                <div class="slip-row"><span>Jam</span><b>{{ $at?->format('H:i') }} WIB</b></div>
                <div class="slip-row"><span>Jenis Transaksi</span><b>{{ $jenis }}</b></div>
                <div class="slip-row"><span>Nama Teller</span><b>{{ $tellerName }}</b></div>
            </div>

            <div class="slip-total">
                <span>Jumlah Tunai</span>
                <span>Rp {{ number_format($transaction->amount, 0, ',', '.') }}</span>
            </div>

            <div class="slip-row" style="border-bottom:0">
                <span>Terbilang</span>
                <b style="max-width:210px">{{ ucfirst(\App\Support\Terbilang::rupiah($transaction->amount)) }}</b>
            </div>

            <div style="margin-top:12px;padding-top:8px;border-top:1px solid #e3e8e2;text-align:center;font-size:9px;line-height:1.5;color:#5a625d">
                Dokumen resmi BANK MINI. Slip sah tanpa tanda tangan;<br>
                watermark BANK MINI sebagai penanda keaslian.
            </div>
        </div>
    </div>
@endsection
