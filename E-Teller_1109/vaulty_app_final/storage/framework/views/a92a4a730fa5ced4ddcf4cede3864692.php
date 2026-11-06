<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harian Teller (Penutupan Kas) - <?php echo e(\Carbon\Carbon::parse($report->report_date)->format('d/m/Y')); ?></title>
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
    <a href="<?php echo e($backUrl); ?>">Kembali</a>
</div>

<div class="sheet">
    <div class="kop-brand"><?php echo e(config('app.name')); ?></div>
    <div class="kop-title">Laporan Harian Teller (Penutupan Kas)</div>
    <div class="kop-meta">
        <?php echo e(\Carbon\Carbon::parse($report->report_date)->locale('id')->translatedFormat('l, d F Y')); ?>

        &middot; Teller: <?php echo e($report->teller->name ?? 'Teller'); ?><?php if($report->teller?->kelas): ?> (<?php echo e($report->teller->kelas); ?>)<?php endif; ?>
        &middot; Status: <?php echo e($statusLabel); ?>

    </div>
    <div class="kop-line"></div>


<?php
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
?>

    
    <h2 class="section">Rekapitulasi</h2>

    <table class="rekap">
        <tbody>
            <tr>
                <td>Saldo awal</td>
                <td class="nilai"><?php echo e($rp($report->saldo_awal)); ?></td>
            </tr>
            <tr>
                <td>Total setoran (<?php echo e($jumlahSetoran); ?> transaksi)</td>
                <td class="nilai"><?php echo e($rp($report->deposit_total)); ?></td>
            </tr>
            <tr>
                <td>Total penarikan</td>
                <td class="nilai"><?php echo e($rp($report->withdrawal_total)); ?></td>
            </tr>

            <?php if($pengembalian > 0): ?>
                <tr>
                    <td>Kas diserahkan ke brankas</td>
                    <td class="nilai">&minus; <?php echo e($rp($pengembalian)); ?></td>
                </tr>
            <?php endif; ?>

            <tr class="total">
                <td>Saldo akhir sistem (saldo awal + setoran &minus; penarikan<?php echo e($pengembalian > 0 ? ' &minus; pengembalian' : ''); ?>)</td>
                <td class="nilai"><?php echo e($rp($saldoAkhir)); ?></td>
            </tr>
            <tr class="total">
                <td>Uang fisik di laci</td>
                <td class="nilai"><?php echo e($rp($totalFisik)); ?></td>
            </tr>
            <tr>
                <td>Selisih</td>
                <td class="nilai selisih <?php echo e(abs($selisih) >= 0.01 ? 'beda' : ''); ?>">
                    <?php echo e(abs($selisih) < 0.01 ? $rp(0) : ($selisih > 0 ? '+ ' : '&minus; ') . $rp(abs($selisih))); ?>

                </td>
            </tr>
        </tbody>
    </table>
    <p class="terbilang">Terbilang: <?php echo e($terbilang); ?></p>

    
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
            <?php $__empty_1 = true; $__currentLoopData = $rincianAwal; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $baris): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($rp($baris['nominal'])); ?></td>
                    <td class="num"><?php echo e($baris['jumlah']); ?> <?php echo e($baris['satuan']); ?></td>
                    <td class="num"><?php echo e($rp($baris['total'])); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td>Tunai modal dari Supervisor</td>
                    <td class="num">&mdash;</td>
                    <td class="num"><?php echo e($rp($report->saldo_awal)); ?></td>
                </tr>
            <?php endif; ?>
            <tr class="jumlah">
                <td colspan="2">Total saldo awal</td>
                <td class="num"><?php echo e($rp($rincianAwal ? $report->totalFisikAwal() : $report->saldo_awal)); ?></td>
            </tr>
        </tbody>
    </table>

    
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
            <?php $__empty_1 = true; $__currentLoopData = $rincianFisik; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $baris): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($rp($baris['nominal'])); ?></td>
                    <td class="num"><?php echo e($baris['jumlah']); ?> <?php echo e($baris['satuan']); ?></td>
                    <td class="num"><?php echo e($rp($baris['total'])); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="3" class="empty">Teller belum mengisi rincian lembaran uang fisik.</td>
                </tr>
            <?php endif; ?>
            <tr class="jumlah">
                <td colspan="2">Total uang fisik</td>
                <td class="num"><?php echo e($rp($totalFisik)); ?></td>
            </tr>
        </tbody>
    </table>


    
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
            <?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($trx->created_at->format('H:i')); ?></td>
                    <td><?php echo e($trx->id); ?></td>
                    <td><?php echo e($trx->type === 'deposit' ? 'Setor tunai' : 'Tarik tunai'); ?></td>
                    <td><?php echo e($trx->account->account_number ?? '-'); ?></td>
                    <td><?php echo e($trx->account->user->name ?? 'Anonim'); ?></td>
                    <td class="num"><?php echo e($rp($trx->amount)); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" class="empty">Tidak ada transaksi pada tanggal ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="catatan-verifikasi"><?php echo e($catatanVerifikasi); ?></p>

    <div class="tanda-tangan">
        <div class="kolom">
            <div class="peran">Teller</div>
            <div class="nama"><?php echo e($report->teller->name ?? 'Teller'); ?></div>
        </div>
        <div class="kolom">
            <div class="peran">Supervisor</div>
            <div class="nama"><?php echo e($report->approver->name ?? 'Supervisor Bank Mini'); ?></div>
        </div>
    </div>

    <div class="footer">
        Dicetak <?php echo e(now()->locale('id')->translatedFormat('d/m/Y H:i')); ?> WIB oleh sistem <?php echo e(config('app.name')); ?>.
    </div>
</div>
</body>
</html>
<?php /**PATH D:\laragon\www\E-Teller_3009\E-Teller_1109\vaulty_app_final\resources\views/reports/laporan-harian-cetak.blade.php ENDPATH**/ ?>