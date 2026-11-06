<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harian Teller (Penutupan Kas) - {{ \Carbon\Carbon::parse($report->report_date)->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6ef;
            margin: 0;
            padding: 18px;
            font-size: 12px;
            color: #111;
            line-height: 1.45;
        }

        .sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 16mm;
            border: 1px solid #dde3d3;
            box-shadow: 0 1px 3px rgba(30, 39, 24, .08);
        }

        .toolbar { text-align: center; margin-bottom: 14px; }
        .toolbar button, .toolbar a {
            display: inline-block;
            padding: 7px 14px;
            margin: 0 4px;
            border-radius: 6px;
            border: 1px solid #c9d2bc;
            background: #fff;
            color: #1e2718;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar .primary { background: #2f6b3a; border-color: #2f6b3a; color: #fff; }

        /* ---------- Kepala laporan ---------- */
        .kop-brand { font-size: 12.5px; font-weight: 700; }
        .kop-title { font-size: 20px; font-weight: 700; margin: 2px 0 4px; }
        .kop-meta { font-size: 11.5px; color: #6b7280; }
        .kop-line { border-bottom: 1.5px solid #111; margin: 8px 0 18px; }

        h2.section { font-size: 13px; font-weight: 700; margin: 18px 0 7px; }

        /* ---------- Rekapitulasi ---------- */
        table.rekap { width: 100%; border-collapse: collapse; }
        table.rekap td { border: 1px solid #e0e0e0; padding: 6px 9px; }
        table.rekap td.nilai { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        table.rekap tr.total td { font-weight: 700; background: #f7f8f5; }
        table.rekap td.selisih { font-weight: 700; color: #1a7f37; }
        table.rekap td.selisih.beda { color: #b3261e; }

        .terbilang { margin-top: 10px; font-size: 11.5px; color: #4b5563; }

        /* ---------- Tabel rincian & transaksi ---------- */
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #e0e0e0; padding: 6px 9px; }
        table.grid thead th { background: #eef0ec; font-weight: 700; text-align: left; }
        table.grid .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        table.grid .center { text-align: center; }
        table.grid tr.jumlah td { font-weight: 700; background: #f7f8f5; }
        table.grid td.empty { text-align: center; color: #6b7280; }

        .catatan-verifikasi { margin-top: 14px; font-size: 11.5px; }
        .catatan-verifikasi .beda { color: #b3261e; font-weight: 600; }

        /* ---------- Tanda tangan ---------- */
        .tanda-tangan { display: flex; justify-content: space-between; margin-top: 42px; text-align: center; }
        .tanda-tangan .kolom { width: 45%; }
        .tanda-tangan .peran { font-size: 11.5px; }
        .tanda-tangan .nama { margin-top: 66px; font-size: 11.5px; font-weight: 600; }

        .footer { margin-top: 34px; font-size: 10.5px; color: #9ca3af; }

        @page { size: A4 portrait; margin: 12mm; }

        @media print {
            body { background: #fff; padding: 0; font-size: 11.5px; }
            .toolbar { display: none !important; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; border: 0; box-shadow: none; }
            table.rekap tr, table.grid tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">Cetak Laporan</button>
    <a href="{{ $backUrl }}">Kembali</a>
</div>

<div class="sheet">
    <div class="kop-brand">{{ config('app.name') }}</div>
    <div class="kop-title">Laporan Harian Teller (Penutupan Kas)</div>
    <div class="kop-meta">
        {{ \Carbon\Carbon::parse($report->report_date)->locale('id')->translatedFormat('l, d F Y') }}
        &middot; Teller: {{ $report->teller->name ?? 'Teller' }}@if($report->teller?->kelas) ({{ $report->teller->kelas }})@endif
        &middot; Status: {{ $statusLabel }}
    </div>
    <div class="kop-line"></div>


@php
    $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');

    $totalFisik = $report->totalFisik();
    $saldoAkhir = $report->saldoAkhirSistem();
    $selisih = $report->selisihKas();

    // Kas yang sudah diserahkan Teller ke brankas hari itu (bila ada).
    $pengembalian = (float) $report->deposit_total
        - (float) $report->withdrawal_total
        - (float) $report->expected_cash;

    $jumlahSetoran = $transactions->where('type', 'deposit')->count();

    $rincianAwal = $report->rincianFisikAwal();
    $rincianFisik = $report->rincianFisik();
@endphp

    {{-- ============ REKAPITULASI ============ --}}
    <h2 class="section">Rekapitulasi</h2>

    <table class="rekap">
        <tbody>
            <tr>
                <td>Saldo awal</td>
                <td class="nilai">{{ $rp($report->saldo_awal) }}</td>
            </tr>
            <tr>
                <td>Total setoran ({{ $jumlahSetoran }} transaksi)</td>
                <td class="nilai">{{ $rp($report->deposit_total) }}</td>
            </tr>
            <tr>
                <td>Total penarikan</td>
                <td class="nilai">{{ $rp($report->withdrawal_total) }}</td>
            </tr>

            @if($pengembalian > 0)
                <tr>
                    <td>Kas diserahkan ke brankas</td>
                    <td class="nilai">&minus; {{ $rp($pengembalian) }}</td>
                </tr>
            @endif

            <tr class="total">
                <td>Saldo akhir sistem (saldo awal + setoran &minus; penarikan{{ $pengembalian > 0 ? ' &minus; pengembalian' : '' }})</td>
                <td class="nilai">{{ $rp($saldoAkhir) }}</td>
            </tr>
            <tr class="total">
                <td>Uang fisik di laci</td>
                <td class="nilai">{{ $rp($totalFisik) }}</td>
            </tr>
            <tr>
                <td>Selisih</td>
                <td class="nilai selisih {{ abs($selisih) >= 0.01 ? 'beda' : '' }}">
                    {{ abs($selisih) < 0.01 ? $rp(0) : ($selisih > 0 ? '+ ' : '&minus; ') . $rp(abs($selisih)) }}
                </td>
            </tr>
        </tbody>
    </table>
    <p class="terbilang">Terbilang: {{ $terbilang }}</p>

    {{-- ============ RINCIAN LEMBARAN: SALDO AWAL ============ --}}
    <h2 class="section">Rincian lembaran: Saldo awal</h2>

    <table class="grid">
        <thead>
            <tr>
                <th>Nominal</th>
                <th class="num">Jumlah</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rincianAwal as $baris)
                <tr>
                    <td>{{ $rp($baris['nominal']) }}</td>
                    <td class="num">{{ $baris['jumlah'] }} {{ $baris['satuan'] }}</td>
                    <td class="num">{{ $rp($baris['total']) }}</td>
                </tr>
            @empty
                <tr>
                    <td>Tunai modal dari Supervisor</td>
                    <td class="num">&mdash;</td>
                    <td class="num">{{ $rp($report->saldo_awal) }}</td>
                </tr>
            @endforelse
            <tr class="jumlah">
                <td colspan="2">Total saldo awal</td>
                <td class="num">{{ $rp($rincianAwal ? $report->totalFisikAwal() : $report->saldo_awal) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ============ RINCIAN LEMBARAN: UANG FISIK ============ --}}
    <h2 class="section">Rincian lembaran: Uang fisik</h2>

    <table class="grid">
        <thead>
            <tr>
                <th>Nominal</th>
                <th class="num">Jumlah</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rincianFisik as $baris)
                <tr>
                    <td>{{ $rp($baris['nominal']) }}</td>
                    <td class="num">{{ $baris['jumlah'] }} {{ $baris['satuan'] }}</td>
                    <td class="num">{{ $rp($baris['total']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="empty">Teller belum mengisi rincian lembaran uang fisik.</td>
                </tr>
            @endforelse
            <tr class="jumlah">
                <td colspan="2">Total uang fisik</td>
                <td class="num">{{ $rp($totalFisik) }}</td>
            </tr>
        </tbody>
    </table>


    {{-- ============ DAFTAR TRANSAKSI ============ --}}
    <h2 class="section">Transaksi</h2>

    <table class="grid">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>No. Trx</th>
                <th>Jenis</th>
                <th>No. Rekening</th>
                <th>Nasabah</th>
                <th class="num">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $trx)
                <tr>
                    <td>{{ $trx->created_at->format('H:i') }}</td>
                    <td>{{ $trx->id }}</td>
                    <td>{{ $trx->type === 'deposit' ? 'Setor tunai' : 'Tarik tunai' }}</td>
                    <td>{{ $trx->account->account_number ?? '-' }}</td>
                    <td>{{ $trx->account->user->name ?? 'Anonim' }}</td>
                    <td class="num">{{ $rp($trx->amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">Tidak ada transaksi pada tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="catatan-verifikasi">{{ $catatanVerifikasi }}</p>

    <div class="tanda-tangan">
        <div class="kolom">
            <div class="peran">Teller</div>
            <div class="nama">{{ $report->teller->name ?? 'Teller' }}</div>
        </div>
        <div class="kolom">
            <div class="peran">Supervisor</div>
            <div class="nama">{{ $report->approver->name ?? 'Supervisor Bank Mini' }}</div>
        </div>
    </div>

    <div class="footer">
        Dicetak {{ now()->locale('id')->translatedFormat('d/m/Y H:i') }} WIB oleh sistem {{ config('app.name') }}.
    </div>
</div>
</body>
</html>
