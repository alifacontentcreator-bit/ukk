{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman laporan harian cetak, termasuk data dan aksi yang disediakan aplikasi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Harian - Supervisor</title>
    <style>
        body { font-family: 'Public Sans', Arial, sans-serif; background-color: #F4F6EF; margin: 0; padding: 20px; font-size: 12px; color: #1E2718; }
        .kertas-a4 { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm; border: 1px solid #DDE3D3; box-shadow: 0 1px 2px rgba(30,39,24,.06); box-sizing: border-box; }
        
        .t-center { text-align: center; }
        .t-bold { font-weight: bold; }
        .space-sm { margin-bottom: 10px; }
        .space-md { margin-bottom: 20px; }
        
        .kontrol-panel { text-align: center; margin-bottom: 20px; }
        .kontrol-panel input[type="date"], .kontrol-panel button { padding: 8px; margin: 0 5px; border-radius: 4px; border: 1px solid #ccc; }
        .btn-print { background-color: #2F6B3A; color: white; border: none; cursor: pointer; font-weight: bold; border-radius: 6px; }
        .btn-print:hover { background-color: #25562F; }
        
        .table-uang { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .table-uang th, .table-uang td { border: 1px solid #000; padding: 5px; }
        .table-uang th { background-color: #E6EDDA; }
        
        .input-qty { width: 100%; border: none; border-bottom: 1px dashed #C9D2BC; text-align: center; font-size: 12px; outline: none; }
        .input-qty:focus { border-bottom: 1px solid #2F6B3A; background-color: #F4F6EF; }

        /* Input Teks untuk Nama Teller */
        .input-teller { 
            border: 1px solid #C9D2BC; /* Beri border agar terlihat seperti kotak isian */
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px; 
            font-family: 'Public Sans', Arial, sans-serif; 
            width: 250px; 
            outline: none; 
            background: #F4F6EF; 
            margin-bottom: 4px;
        }
        .input-teller:focus { 
            border-color: #2F6B3A; 
            background-color: #fff; 
        }
        
        .col-nominal { width: 120px; text-align: right; }
        .col-x { width: 20px; text-align: center; border-right: none; }
        .col-input { width: 80px; border-left: none; border-right: none; }
        .col-satuan { width: 60px; border-left: none; border-right: none; }
        .col-sama { width: 20px; text-align: center; border-left: none; }
        .col-total { width: 150px; text-align: right; font-weight: bold; }

        .rekap-list { list-style: none; padding-left: 15px; margin: 0; }
        .rekap-list li { margin-bottom: 5px; display: flex; align-items: baseline; }
        .rekap-list li span.label { flex: 0 0 auto; width: 240px; }
        .rekap-list li span.titik-dua { flex: 0 0 auto; width: 34px; white-space: nowrap; }
        .rekap-list li span.value { flex: 1; font-family: 'Public Sans', Arial, sans-serif; font-size: 13px; font-variant-numeric: tabular-nums; text-align: right; padding-right: 4px; }

        /* Saat di-print, border input akan hilang dan menyatu dengan kertas */
        @media print {
            body { background: #fff; padding: 0; }
            .kertas-a4 { box-shadow: none; margin: 0; padding: 0; width: 100%; }
            .kontrol-panel { display: none !important; }
            .input-qty { border-bottom: none !important; background: transparent !important; }
            .input-teller { border: none !important; background: transparent !important; padding: 0 !important; border-bottom: 1px dotted #000 !important; border-radius: 0; }
            .input-teller::placeholder { color: transparent; }
        }
    </style>
</head>
<body>

<div class="kontrol-panel">
    <form method="GET" action="{{ route('supervisor.laporan.cetak') }}" style="display: inline-block;">
        <input type="date" name="tanggal" value="{{ request('tanggal', $tanggal) }}">
        <button type="submit">Filter Tanggal</button>
    </form>
    <button class="btn-print" onclick="window.print()">Cetak Laporan</button>
    <a class="btn-print" style="display:inline-block;padding:8px;margin:0 5px;border-radius:4px;background:#fff;border:1px solid #ccc;color:#1E2718;text-decoration:none"
       href="{{ route('supervisor.laporan.cetak-pdf', ['tanggal' => $tanggal]) }}" target="_blank" rel="noopener">Unduh PDF</a>
    <a class="btn-print" style="display:inline-block;padding:8px;margin:0 5px;border-radius:4px;background:#fff;border:1px solid #ccc;color:#1E2718;text-decoration:none"
       href="{{ route('supervisor.laporan.cetak-excel', ['tanggal' => $tanggal]) }}">Unduh Excel</a>
</div>

<div class="kertas-a4">
    <div class="t-center space-md">
        <h2 class="t-bold" style="margin-bottom: 5px; margin-top:0;">LKM MITRA SISWA ABADI</h2>
        <h3 class="t-bold" style="margin-top: 0;">LAPORAN HARIAN</h3>
    </div>

    <table style="width: 100%; border: none; margin-bottom: 20px;">
        <tr>
            <td style="width: 150px; border:none; padding:2px;">Hari, tanggal</td>
            <td style="width: 10px; border:none; padding:2px;">:</td>
            <td style="border:none; padding:2px;">{{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}</td>
        </tr>
        <tr>
            <td style="border:none; padding:2px; vertical-align: top;">Petugas Teller</td>
            <td style="border:none; padding:2px; vertical-align: top;">:</td>
            <td style="border:none; padding:2px;">
                1. <input type="text" class="input-teller" placeholder="Ketik nama teller 1..."><br>
                2. <input type="text" class="input-teller" placeholder="Ketik nama teller 2..."><br>
                3. <input type="text" class="input-teller" placeholder="Ketik nama teller 3...">
            </td>
        </tr>
    </table>

    <div class="t-bold space-sm">SALDO AWAL (Fisik)</div>
    <table class="table-uang" id="tabel-awal">
        <thead>
            <tr>
                <th>NOMINAL</th><th colspan="4">JUMLAH</th><th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach([100000, 50000, 20000, 10000, 5000] as $nominal)
            <tr>
                <td class="col-nominal">Rp {{ number_format($nominal, 0, ',', '.') }}</td>
                <td class="col-x">X</td>
                <td class="col-input"><input type="number" class="input-qty qty-awal" data-nominal="{{ $nominal }}" min="0"></td>
                <td class="col-satuan">Lembar</td>
                <td class="col-sama">=</td>
                <td class="col-total result-awal">0</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="5" class="t-bold">TOTAL SALDO AWAL FISIK</td>
                <td class="col-total t-bold" id="grand-total-awal">0</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-bottom: 20px;">
        <div class="t-bold space-sm">Rekap Transaksi :</div>
        <ul class="rekap-list">
            <li><span class="label">1. Saldo awal</span><span class="titik-dua">: Rp</span>
                <span class="value">{{ number_format($saldoAwalSistem, 0, ',', '.') }}</span></li>
            <li><span class="label">2. Setoran tabungan</span><span class="titik-dua">: Rp</span>
                <span class="value">{{ number_format($setoran, 0, ',', '.') }}</span></li>
            <li><span class="label">3. Administrasi buku tabungan</span><span class="titik-dua">: Rp</span>
                <span class="value">{{ number_format($administrasi, 0, ',', '.') }}</span></li>
            <li><span class="label">4. Penarikan tabungan</span><span class="titik-dua">: Rp</span>
                <span class="value">{{ number_format($penarikan, 0, ',', '.') }}</span></li>
            <li><span class="label">5. Saldo Akhir</span><span class="titik-dua">: Rp</span>
                <span class="value t-bold" id="saldo-akhir-sistem" data-value="{{ $saldoAkhirSistem }}">{{ number_format($saldoAkhirSistem, 0, ',', '.') }}</span></li>
        </ul>
    </div>

    <div class="t-bold space-sm">SALDO AKHIR (Fisik)</div>
    <table class="table-uang" id="tabel-akhir">
        <thead>
            <tr>
                <th>NOMINAL</th><th colspan="4">JUMLAH</th><th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach([100000, 50000, 20000, 10000, 5000, 2000, 1000] as $nominal)
            <tr>
                <td class="col-nominal">Rp {{ number_format($nominal, 0, ',', '.') }}</td>
                <td class="col-x">X</td>
                <td class="col-input"><input type="number" class="input-qty qty-akhir" data-nominal="{{ $nominal }}" min="0"></td>
                <td class="col-satuan">Lembar</td>
                <td class="col-sama">=</td>
                <td class="col-total result-akhir">0</td>
            </tr>
            @endforeach
            @foreach([1000, 500, 200, 100] as $nominal)
            <tr>
                <td class="col-nominal">Rp {{ number_format($nominal, 0, ',', '.') }}</td>
                <td class="col-x">X</td>
                <td class="col-input"><input type="number" class="input-qty qty-akhir" data-nominal="{{ $nominal }}" min="0"></td>
                <td class="col-satuan">Keping</td>
                <td class="col-sama">=</td>
                <td class="col-total result-akhir">0</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="5" class="t-bold">TOTAL SALDO AKHIR FISIK</td>
                <td class="col-total t-bold" id="grand-total-akhir">0</td>
            </tr>
        </tbody>
    </table>
    
    <div id="selisih-kas" class="t-bold t-center" style="margin-top: 10px; font-size: 14px;"></div>
</div>

<script>
    const formatRp = (angka) => new Intl.NumberFormat('id-ID').format(angka);

    const inputsAwal = document.querySelectorAll('.qty-awal');
    inputsAwal.forEach(input => {
        input.addEventListener('input', function() {
            let grandTotal = 0;
            inputsAwal.forEach(el => {
                let qty = parseInt(el.value) || 0;
                let nominal = parseInt(el.getAttribute('data-nominal'));
                let totalRow = qty * nominal;
                el.closest('tr').querySelector('.result-awal').innerText = formatRp(totalRow);
                grandTotal += totalRow;
            });
            document.getElementById('grand-total-awal').innerText = 'Rp ' + formatRp(grandTotal);
        });
    });

    const inputsAkhir = document.querySelectorAll('.qty-akhir');
    const saldoSistem = parseInt(document.getElementById('saldo-akhir-sistem').getAttribute('data-value'));
    const selisihKasEl = document.getElementById('selisih-kas');

    inputsAkhir.forEach(input => {
        input.addEventListener('input', function() {
            let grandTotal = 0;
            inputsAkhir.forEach(el => {
                let qty = parseInt(el.value) || 0;
                let nominal = parseInt(el.getAttribute('data-nominal'));
                let totalRow = qty * nominal;
                el.closest('tr').querySelector('.result-akhir').innerText = formatRp(totalRow);
                grandTotal += totalRow;
            });
            document.getElementById('grand-total-akhir').innerText = 'Rp ' + formatRp(grandTotal);

            let selisih = grandTotal - saldoSistem;
            if(selisih === 0 && grandTotal > 0) {
                selisihKasEl.innerText = "KAS BALANCE / SEIMBANG \u2713";
                selisihKasEl.style.color = "#25562F";
            } else if (selisih > 0) {
                selisihKasEl.innerText = "KAS LEBIH: Rp " + formatRp(selisih);
                selisihKasEl.style.color = "#7A5A12";
            } else if (selisih < 0 && grandTotal > 0) {
                selisihKasEl.innerText = "KAS KURANG: Rp " + formatRp(Math.abs(selisih));
                selisihKasEl.style.color = "#8A2F22";
            } else {
                selisihKasEl.innerText = "";
            }
        });
    });
</script>
</body>
</html>