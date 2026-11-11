{{-- Tujuan file: Halaman siap cetak (A4 landscape) untuk mutasi rekening nasabah.
     Dibuka dari tombol Cetak di halaman Cetak Mutasi Teller. --}}
@php
    $rp = fn ($value) => number_format((float) $value, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mutasi Rekening {{ $account->account_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            color: #1e291b;
            background: #eef0ea;
        }
        .sheet {
            position: relative;
            max-width: 1040px;
            margin: 0 auto;
            padding: 24px 28px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 12px 32px rgba(30, 41, 27, .12);
            overflow: hidden;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            font-size: 120px;
            font-weight: 800;
            letter-spacing: 10px;
            color: rgba(30, 41, 27, .05);
        }
        .content { position: relative; z-index: 1; }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #1e291b; padding-bottom: 10px; }
        .brand b { display: block; font-size: 16px; letter-spacing: .3px; }
        .brand span { font-size: 11px; color: #5a625d; }
        .title { text-align: right; }
        .title b { display: block; font-size: 14px; }
        .title span { font-size: 11px; color: #5a625d; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 24px; margin: 12px 0 0; font-size: 12px; }
        .meta div { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; border-bottom: 1px dotted #d7ddd3; }
        .meta span { color: #5a625d; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 12px 0; }
        .summary div { border: 1px solid #d7ddd3; background: #f6f8f2; border-radius: 6px; padding: 7px 9px; }
        .summary span { display: block; font-size: 9px; letter-spacing: .4px; color: #5a625d; text-transform: uppercase; }
        .summary b { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        thead th {
            background: #e6ebdd;
            border-bottom: 1px solid #b9c2b3;
            text-align: left;
            padding: 6px 6px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        tbody td { padding: 5px 6px; border-bottom: 1px solid #eef0ea; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #fbfcf8; }
        .num { text-align: right; white-space: nowrap; }
        .nw { white-space: nowrap; }
        .mono { font-family: Consolas, "Courier New", monospace; font-size: 10px; }
        .foot { margin-top: 12px; padding-top: 8px; border-top: 1px solid #d7ddd3; font-size: 9px; color: #5a625d; display: flex; justify-content: space-between; gap: 12px; }
        .toolbar { max-width: 1040px; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar a, .toolbar button {
            font: inherit;
            font-size: 12px;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #1e291b;
            background: #fff;
            color: #1e291b;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar .primary { background: #1e291b; color: #fff; }
        @page { size: A4 landscape; margin: 10mm; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .sheet { max-width: none; padding: 0; border-radius: 0; box-shadow: none; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            tbody tr:nth-child(even) td { background: transparent; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('teller.mutasi', ['account_number' => $account->account_number, 'from' => $statement['period_from_iso'], 'to' => $statement['period_to_iso']]) }}">Kembali</a>
        <a class="primary" href="{{ route('teller.mutasi.pdf', ['account_number' => $account->account_number, 'from' => $statement['period_from_iso'], 'to' => $statement['period_to_iso']]) }}" target="_blank" rel="noopener">Unduh PDF</a>
        <button class="primary" type="button" onclick="window.print()">Cetak</button>
    </div>

    <div class="sheet">
        <div class="watermark">BANK MINI</div>
        <div class="content">
            <div class="head">
                <div class="brand">
                    <b>LKM MITRA SISWA ABADI</b>
                    <span>BANK MINI &middot; SMK NEGERI 1 BANTUL</span>
                </div>
                <div class="title">
                    <b>MUTASI REKENING</b>
                    <span>Periode {{ $statement['period_from'] }} s/d {{ $statement['period_to'] }}</span>
                </div>
            </div>

            <div class="meta">
                <div><span>No. Rekening</span><b class="mono">{{ $account->account_number }}</b></div>
                <div><span>Nama Nasabah</span><b>{{ $account->user?->name ?? '-' }}</b></div>
                <div><span>Jenis Tabungan</span><b>{{ $statement['product_type'] }}</b></div>
                <div><span>Kelas</span><b>{{ $account->user?->kelas ?? '-' }}</b></div>
                <div><span>Dicetak</span><b>{{ now()->format('d/m/Y H:i') }} WIB</b></div>
                <div><span>Jumlah Transaksi</span><b>{{ $statement['total_transactions'] }} baris</b></div>
            </div>

            <div class="summary">
                <div><span>Saldo Awal</span><b>Rp {{ $rp($statement['opening_balance']) }}</b></div>
                <div><span>Mutasi Kredit (CR)</span><b>Rp {{ $rp($statement['total_credit']) }}</b></div>
                <div><span>Mutasi Debit (DB)</span><b>Rp {{ $rp($statement['total_debit']) }}</b></div>
                <div><span>Saldo Akhir</span><b>Rp {{ $rp($statement['closing_balance']) }}</b></div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal Transaksi</th>
                        <th>Keterangan / Deskripsi Transaksi</th>
                        <th>Referensi</th>
                        <th class="num">Mutasi Debit (DB)</th>
                        <th class="num">Mutasi Kredit (CR)</th>
                        <th class="num">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($statement['rows'] as $index => $row)
                        <tr>
                            <td class="nw">{{ $index + 1 }}</td>
                            <td class="nw">{{ $row['date'] }} {{ $row['time'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="mono">{{ $row['reference'] }}</td>
                            <td class="num">{{ $row['debit'] > 0 ? $rp($row['debit']) : '-' }}</td>
                            <td class="num">{{ $row['credit'] > 0 ? $rp($row['credit']) : '-' }}</td>
                            <td class="num">{{ $rp($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Tidak ada mutasi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <div class="foot">
                <span>Dokumen diterbitkan otomatis oleh sistem BANK MINI. Saldo akhir adalah saldo setelah transaksi tercatat.</span>
                <span>Dicetak oleh: {{ auth()->user()?->name ?? 'Teller' }}</span>
            </div>
        </div>
    </div>
</body>
</html>
