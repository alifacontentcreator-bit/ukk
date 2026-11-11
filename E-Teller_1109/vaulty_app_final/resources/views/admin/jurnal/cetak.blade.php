{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman cetak, termasuk data dan aksi yang disediakan aplikasi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Jurnal Akuntansi</title>
    <style>
        /* Halaman cetak berdiri sendiri: gaya ditulis di sini agar tetap tampil
           benar saat dicetak atau dirender menjadi PDF. */
        *{box-sizing:border-box}
        body{font-family:"Public Sans","Helvetica Neue",Helvetica,Arial,sans-serif;margin:0;
            padding:20px;color:#1E2718;font-size:11.5px;line-height:1.45}
        .brandline{display:flex;align-items:center;gap:8px;margin-bottom:10px;color:#2F6B3A}
        .brandline b{font-size:12.5px;letter-spacing:.02em}
        .brandline span{color:#5A6552;font-size:10px}
        .header{text-align:center;border-bottom:2px solid #2F6B3A;padding-bottom:8px;margin-bottom:14px}
        .header h1{margin:0;font-size:16px;text-transform:uppercase;letter-spacing:.04em;color:#1E2718}
        .header p{margin:4px 0 0;font-size:10.5px;color:#5A6552}
        .header p strong{color:#25562F}
        table{width:100%;border-collapse:collapse;margin-top:6px}
        th,td{border:1px solid #DDE3D3;padding:5px 7px;text-align:left;vertical-align:top}
        th{background:#F4F6EF;font-weight:700;text-transform:uppercase;font-size:9.5px;
            letter-spacing:.04em;color:#5A6552}
        tbody tr:nth-child(even) td{background:#FAFBF7}
        .t-right{text-align:right;font-variant-numeric:tabular-nums}
        .t-center{text-align:center}
        .mono{font-variant-numeric:tabular-nums;letter-spacing:.02em}
        .print-btn{display:block;margin:0 0 14px;padding:8px 16px;background:#2F6B3A;color:#fff;
            border:0;border-radius:6px;cursor:pointer;font-weight:700;font-size:11.5px}
        .print-btn:hover{background:#25562F}
        .foot{margin-top:12px;font-size:9.5px;color:#5A6552;text-align:right}
        @media print{
            .print-btn{display:none}
            body{padding:0}
            @page{margin:12mm}
        }
    </style>
</head>
<body>

    <button class="print-btn" type="button" onclick="window.print()">Cetak laporan sekarang</button>

    <div class="brandline">
        <b>{{ config('app.name') }}</b>
        <span>SMK Negeri 1 Bantul &middot; Sistem Tabungan Siswa</span>
    </div>

    <div class="header">
        <h1>Laporan Jurnal Akuntansi</h1>
        <p>Kategori Nasabah: <strong>{{ strtoupper($kategori ?? 'SEMUA TRANSAKSI') }}</strong></p>
        @php
            $tanggalFilter = $filters['date'] ?? $filters['date_to'] ?? $filters['date_from'] ?? null;
        @endphp
        @if($tanggalFilter)
            <p>Tanggal Jurnal: <strong>{{ \Carbon\Carbon::parse($tanggalFilter)->format('d/m/Y') }}</strong></p>
        @endif
        <p>Tanggal Cetak: {{ date('d/m/Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Waktu Transaksi</th>
                <th>No. Ref</th>
                <th>Nama Nasabah</th>
                <th>Kategori</th>
                <th class="t-center">Kode Akun</th>
                <th class="t-center">Posisi</th>
                <th class="t-right">Nominal (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($jurnals as $jurnal)
                @php
                    $user = $jurnal->transaction->account->user ?? null;
                    $kategoriLabel = ($user && $user->kelas === 'Pegawai') ? 'GURU/KARYAWAN' : 'SISWA';
                @endphp
                <tr>
                    <td class="mono">{{ $jurnal->created_at->format('d/m/Y H:i') }}</td>
                    <td class="mono">{{ $jurnal->transaction->transaction_code ?? '-' }}</td>
                    <td>{{ $user->name ?? 'Sistem' }}</td>
                    <td>{{ $kategoriLabel }}</td>
                    <td class="t-center mono">{{ $jurnal->account_code }}</td>
                    <td class="t-center">{{ strtoupper($jurnal->position) }}</td>
                    <td class="t-right">{{ number_format($jurnal->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="foot">{{ $jurnals->count() }} baris jurnal &middot; dicetak dari {{ config('app.name') }}</p>

</body>
</html>
