
@extends('layouts.supervisor')

@section('title', 'Rekonsiliasi Kas')
@section('page-title', 'Rekonsiliasi Kas')
@section('page-subtitle', 'Pantau saldo brankas, penyerahan modal, dan penutupan kas semua teller.')

@section('content')

@php
    $vaultInitial = $vaultInitial ?? 0;
    $vaultBalance = $vaultBalance ?? $vaultInitial;
    $vaultShortage = $vaultShortage ?? 0;
    $tellers = $tellers ?? collect();
    $openingTransfers = $openingTransfers ?? collect();
    $closingTransfers = $closingTransfers ?? collect();
    $cashRequests = $cashRequests ?? collect();
    $reports = $reports ?? collect();

    $summary = $summary ?? [
        'total_teller' => 0,
        'sudah_lapor' => 0,
        'klop' => 0,
        'selisih' => 0,
        'sudah_brankas' => 0,
    ];

    $tanggal = $tanggal ?? today()->toDateString();
@endphp

<div class="stack">

    {{-- Notifikasi (sukses / error) ditampilkan sekali oleh layouts/shell di bagian atas halaman. --}}

    @if($vaultShortage > 0)
        <div class="alert alert-warn">
            <x-icon name="alert" />
            <div>
                <p class="name">Saldo awal brankas lebih kecil dari modal yang sudah diserahkan</p>
                <p class="sub">
                    Total modal keluar melebihi saldo awal sebesar
                    Rp {{ number_format((float) $vaultShortage, 0, ',', '.') }},
                    sehingga saldo brankas ditampilkan Rp 0.
                    Perbarui saldo awal brankas sesuai uang fisik yang ada di brankas sekarang.
                </p>
            </div>
        </div>
    @endif


    {{-- RINGKASAN BRANKAS --}}
    <div class="recon-stats">

        <div class="card stat recon-stat">
            <div class="stat-top">
                <span class="stat-label">Saldo Awal Brankas</span>
                <span class="stat-icon">▣</span>
            </div>

            <div>
                <div class="stat-value">
                    Rp {{ number_format((float) $vaultInitial, 0, ',', '.') }}
                </div>
            </div>
        </div>

        <div class="card stat recon-stat">
            <div class="stat-top">
                <span class="stat-label">Saldo Brankas Saat Ini</span>
                <span class="stat-icon">▤</span>
            </div>

            <div>
                <div class="stat-value">
                    Rp {{ number_format((float) $vaultBalance, 0, ',', '.') }}
                </div>
            </div>
        </div>

        <div class="card stat recon-stat">
            <div class="stat-top">
                <span class="stat-label">Teller Sudah Lapor</span>
                <span class="stat-icon">◷</span>
            </div>

            <div>
                <div class="stat-value">
                    {{ $summary['sudah_lapor'] ?? 0 }} /
                    {{ $summary['total_teller'] ?? 0 }}
                </div>
                <div class="stat-note">Laporan harian yang sudah dikirim</div>
            </div>
        </div>

        <div class="card stat recon-stat">
            <div class="stat-top">
                <span class="stat-label">Pemeriksaan Selisih</span>
                <span class="stat-icon">↔</span>
            </div>

            <div>
                <div class="stat-value">
                    {{ $summary['klop'] ?? 0 }} klop /
                    <span class="{{ ($summary['selisih'] ?? 0) > 0 ? 'is-bad' : '' }}">
                        {{ $summary['selisih'] ?? 0 }} selisih
                    </span>
                </div>
                <div class="stat-note">Dari laporan yang masuk</div>
            </div>
        </div>

    </div>


    {{-- FORM SALDO AWAL BRANKAS --}}
    <section class="card section">

        <div class="section-head">
            <div>
                <h2 class="section-title">Pengaturan Saldo Awal Brankas</h2>
                <p class="section-sub">
                    Tentukan jumlah modal awal brankas. Saldo brankas saat ini akan sama
                    dengan angka ini, lalu berkurang ketika modal diserahkan ke Teller dan
                    bertambah kembali ketika kas dari Teller masuk ke brankas.
                </p>
            </div>
        </div>

        <form
            action="{{ route('supervisor.kas.saldo-awal') }}"
            method="POST"
            class="card-body stack"
        >
            @csrf

            <div class="field">
                <label for="initial_balance_display">Saldo Awal Brankas (Rp)</label>

                {{-- Tampilan memakai titik ribuan, nilai asli (angka murni) disimpan di input hidden. --}}
                <input
                    type="text"
                    id="initial_balance_display"
                    value="{{ number_format((float) old('initial_balance', $vaultInitial), 0, ',', '.') }}"
                    inputmode="numeric"
                    autocomplete="off"
                    data-rupiah-display
                    data-target="initial_balance"
                    placeholder="Contoh: 1.000.000"
                    required
                >

                <input
                    type="hidden"
                    id="initial_balance"
                    name="initial_balance"
                    value="{{ old('initial_balance', (int) $vaultInitial) }}"
                >

                <p class="helper">
                    Saldo awal yang disimpan langsung menjadi saldo brankas saat ini.
                    Saldo berkurang setelah Anda menyerahkan modal ke Teller, dan
                    bertambah kembali setelah kas dari Teller masuk ke brankas.
                </p>
            </div>

            <button type="submit" class="btn btn-primary">
                Simpan Saldo Awal
            </button>
        </form>

    </section>


    {{-- PERMINTAAN TAMBAH MODAL DARI TELLER --}}
    <section class="card section">

        <div class="section-head">
            <div>
                <h2 class="section-title">Permintaan Tambah Modal dari Teller</h2>
                <p class="section-sub">
                    Teller mengajukan tambahan modal ketika kas laci menipis.
                    Nominal ditentukan Supervisor, lalu Teller mengonfirmasi penerimaannya.
                </p>
            </div>

            @if($cashRequests->isNotEmpty())
                <span class="badge badge-warn">
                    {{ $cashRequests->count() }} menunggu
                </span>
            @endif
        </div>

        <div class="table-wrap">
            <table class="table">

                <thead>
                    <tr>
                        <th>Teller</th>
                        <th>Tanggal</th>
                        <th>Catatan Teller</th>
                        <th class="num">Nominal Tambahan (Rp)</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($cashRequests as $cashRequest)
                        <tr>
                            <td class="name">
                                {{ $cashRequest->teller->name ?? '-' }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($cashRequest->request_date)->format('d/m/Y') }}
                            </td>

                            <td>
                                {{ $cashRequest->note ?? '-' }}
                            </td>

                            <td>
                                <form
                                    action="{{ route('supervisor.kas.penuhi-modal', $cashRequest) }}"
                                    method="POST"
                                    class="toolbar"
                                    style="justify-content:flex-end;gap:6px;flex-wrap:nowrap"
                                >
                                    @csrf

                                    <input
                                        type="text"
                                        id="req_amount_display_{{ $cashRequest->id }}"
                                        data-rupiah-display
                                        data-target="req_amount_{{ $cashRequest->id }}"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        placeholder="Contoh: 100.000"
                                        style="max-width:150px"
                                        required
                                    >

                                    <input
                                        type="hidden"
                                        id="req_amount_{{ $cashRequest->id }}"
                                        name="amount"
                                        value=""
                                    >

                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Serahkan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-cell">
                                Tidak ada permintaan tambah modal. Teller dapat mengajukan dari dashboard Teller.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </section>


    {{-- FORM PENYERAHAN MODAL --}}
    <section class="card section">

        <div class="section-head">
            <div>
                <h2 class="section-title">Penyerahan Modal ke Teller</h2>
                <p class="section-sub">
                    Supervisor menyerahkan modal dari brankas kepada Teller.
                    Modal baru masuk ke saldo laci setelah dikonfirmasi Teller.
                </p>
            </div>
        </div>

        <form
            action="{{ route('supervisor.kas.serahkan-modal') }}"
            method="POST"
            class="card-body stack"
        >
            @csrf

            <div class="field">
                <label for="teller_id">Pilih Teller</label>

                <select id="teller_id" name="teller_id" required>
                    <option value="">-- Pilih Teller --</option>

                    @foreach($tellers as $teller)
                        <option
                            value="{{ $teller->id }}"
                            {{ old('teller_id') == $teller->id ? 'selected' : '' }}
                        >
                            {{ $teller->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="amount_display">Jumlah Modal (Rp)</label>

                {{-- Diketik bebas (1000 / 100000), otomatis tampil 1.000 / 100.000. --}}
                <input
                    type="text"
                    id="amount_display"
                    value="{{ old('amount') ? number_format((float) old('amount'), 0, ',', '.') : '' }}"
                    inputmode="numeric"
                    autocomplete="off"
                    data-rupiah-display
                    data-target="amount"
                    placeholder="Contoh: 100.000"
                    required
                >

                <input
                    type="hidden"
                    id="amount"
                    name="amount"
                    value="{{ old('amount') }}"
                >

                <p class="helper">
                    Titik ribuan ditambahkan otomatis, misalnya 1.000 untuk seribu rupiah
                    dan 100.000 untuk seratus ribu rupiah.
                </p>
            </div>

            <div class="field">
                <label for="opening_description">Keterangan</label>

                <input
                    type="text"
                    id="opening_description"
                    name="description"
                    maxlength="500"
                    value="{{ old('description') }}"
                    placeholder="Contoh: Modal operasional teller hari ini"
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Serahkan Modal
            </button>

        </form>

    </section>


    {{-- MODAL MENUNGGU KONFIRMASI TELLER --}}
    <section class="card section">

        <div class="section-head">
            <div>
                <h2 class="section-title">Modal Menunggu Konfirmasi Teller</h2>
                <p class="section-sub">
                    Teller harus mengonfirmasi penerimaan modal melalui dashboard Teller.
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">

                <thead>
                    <tr>
                        <th>Teller</th>
                        <th>Tanggal</th>
                        <th class="num">Jumlah Modal</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($openingTransfers as $transfer)
                        <tr>
                            <td class="name">
                                {{ $transfer->teller->name ?? '-' }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d/m/Y') }}
                            </td>

                            <td class="amount num">
                                Rp {{ number_format((float) $transfer->amount, 0, ',', '.') }}
                            </td>

                            <td>
                                {{ $transfer->description ?? '-' }}
                            </td>

                            <td>
                                <span class="badge badge-warn">
                                    Menunggu konfirmasi Teller
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-cell">
                                Tidak ada modal yang menunggu konfirmasi Teller.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </section>


    {{-- KAS KEMBALI MENUNGGU KONFIRMASI SUPERVISOR --}}
    <section class="card section">

        <div class="section-head">
            <div>
                <h2 class="section-title">Konfirmasi Pengembalian Kas</h2>
                <p class="section-sub">
                    Periksa jumlah kas yang diserahkan kembali oleh Teller.
                    Konfirmasikan setelah kas benar-benar diterima di brankas.
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">

                <thead>
                    <tr>
                        <th>Teller</th>
                        <th>Tanggal</th>
                        <th class="num">Jumlah Kas</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($closingTransfers as $transfer)
                        <tr>
                            <td class="name">
                                {{ $transfer->teller->name ?? '-' }}
                            </td>

                            <td>
                                {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d/m/Y') }}
                            </td>

                            <td class="amount num">
                                Rp {{ number_format((float) $transfer->amount, 0, ',', '.') }}
                            </td>

                            <td>
                                {{ $transfer->description ?? '-' }}
                            </td>

                            <td>
                                <span class="badge badge-warn">
                                    Menunggu konfirmasi
                                </span>
                            </td>

                            <td class="num">
                                <form
                                    action="{{ route('supervisor.kas.konfirmasi-pengembalian', $transfer->id) }}"
                                    method="POST"
                                    onsubmit="return confirm('Pastikan kas benar-benar sudah diterima di brankas. Lanjutkan konfirmasi?')"
                                >
                                    @csrf

                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Konfirmasi Kas Masuk
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">
                                Tidak ada pengembalian kas yang menunggu konfirmasi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </section>


    {{-- FILTER TANGGAL --}}
    <form
        method="GET"
        action="{{ route('supervisor.rekonsiliasi') }}"
        class="recon-toolbar"
    >

        <div class="field recon-date-field">
            <label for="rTanggal">Tanggal Rekonsiliasi</label>

            <input
                id="rTanggal"
                type="date"
                name="tanggal"
                value="{{ $tanggal }}"
            >
        </div>

        <button type="submit" class="btn btn-primary">
            Tampilkan
        </button>

        <a
            href="{{ route('supervisor.laporan') }}"
            class="btn btn-soft recon-report-link"
        >
            Buka laporan dan persetujuan
        </a>

    </form>


    {{-- REKONSILIASI PER TELLER --}}
    <section class="card recon-table-card">

        <div class="section-head">
            <div>
                <h2 class="section-title">Status Kas per Teller</h2>

                <p class="section-sub">
                    {{ \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y') }}
                </p>
            </div>
        </div>

        <div class="table-wrap">

            <table class="table">

                <thead>
                    <tr>
                        <th>Teller</th>
                        <th>Laporan</th>
                        <th class="num">Total Sistem</th>
                        <th class="num">Total Fisik</th>
                        <th>Selisih</th>
                        <th>Brankas</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($reports as $r)

                        <tr>
                            <td class="name">
                                {{ $r->teller->name ?? '-' }}
                            </td>

                            <td>
                                <span class="badge {{ $r->status === 'approved' ? 'badge-ok' : ($r->status === 'submitted' ? 'badge-warn' : 'badge-danger') }}">
                                    {{ [
                                        'approved' => 'Disetujui',
                                        'submitted' => 'Terkirim',
                                        'draft' => 'Draf',
                                        'rejected' => 'Ditolak',
                                    ][$r->status] ?? ucfirst($r->status) }}
                                </span>
                            </td>

                            <td class="amount num">
                                Rp {{ number_format((float) $r->total_sistem, 0, ',', '.') }}
                            </td>

                            <td class="amount num">
                                Rp {{ number_format((float) $r->total_fisik, 0, ',', '.') }}
                            </td>

                            <td>
                                @if(abs((float) $r->selisih) < 0.01)
                                    <span class="badge badge-ok">Klop</span>
                                @else
                                    <span class="badge badge-danger">
                                        Rp {{ number_format((float) $r->selisih, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                <span class="badge {{ $r->transfer_status === 'approved' ? 'badge-ok' : 'badge-warn' }}">
                                    {{ $r->transfer_status === 'approved' ? 'Sudah di brankas' : 'Menunggu' }}
                                </span>
                            </td>

                            <td class="num">
                                <a
                                    href="{{ route('supervisor.laporan') }}"
                                    class="btn btn-soft btn-sm"
                                >
                                    Proses
                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="empty-cell">
                                Belum ada Teller yang mengirim laporan untuk tanggal ini.
                            </td>
                        </tr>

                    @endforelse
                </tbody>

            </table>

        </div>

    </section>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Hanya ambil digit angka (buang titik, spasi, dan karakter lain).
    function hanyaAngka(value) {
        return (value || '').replace(/\D/g, '');
    }

    // 1000 -> 1.000 dan 100000 -> 100.000.
    function formatRibuan(angka) {

        if (angka === '') {
            return '';
        }

        // Buang nol di depan supaya "0100" tidak menjadi "0.100".
        angka = angka.replace(/^0+(?=\d)/, '');

        return angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    document.querySelectorAll('[data-rupiah-display]').forEach(function (display) {

        const hidden = document.getElementById(display.dataset.target);

        if (!hidden) {
            return;
        }

        function sinkronkan(nilai) {

            const angka = hanyaAngka(nilai);

            // Tampilan diberi titik ribuan, nilai untuk server tetap angka murni.
            display.value = formatRibuan(angka);
            hidden.value = angka;
        }

        // Nilai awal (old() atau saldo brankas saat ini) langsung diformat.
        sinkronkan(hidden.value);

        display.addEventListener('input', function () {
            sinkronkan(display.value);
        });

        const form = display.closest('form');

        if (form) {
            // Pastikan Laravel selalu menerima angka tanpa titik.
            form.addEventListener('submit', function () {
                hidden.value = hanyaAngka(display.value);
            });
        }

    });

});
</script>
@endpush
