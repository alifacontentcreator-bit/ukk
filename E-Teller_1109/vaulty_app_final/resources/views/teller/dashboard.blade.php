@extends('layouts.teller')

@section('title', 'Dasbor Teller')
@section('page-title', 'Ringkasan Operasional Teller')
@section('page-subtitle', 'Pantau kas loket dan lanjutkan pekerjaan harian Anda.')

@section('content')
@php
    $setoran = $setoran ?? 0;
    $penarikan = $penarikan ?? 0;
    $uangDiLaci = $uangDiLaci ?? 0;
    $openingTransfer = $openingTransfer ?? null;
    $closingTransfer = $closingTransfer ?? null;
@endphp

    <div class="grid-4">
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Setoran Hari Ini</span>
                <span class="stat-icon"><x-icon name="plus" /></span>
            </div>
            <div>
                <div class="stat-value">Rp {{ number_format((float) $setoran, 0, ',', '.') }}</div>
                <div class="stat-note">Dana masuk tercatat di sistem</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Penarikan Hari Ini</span>
                <span class="stat-icon"><x-icon name="minus" /></span>
            </div>
            <div>
                <div class="stat-value">Rp {{ number_format((float) $penarikan, 0, ',', '.') }}</div>
                <div class="stat-note">Dana keluar tercatat di sistem</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Perkiraan Kas di Laci</span>
                <span class="stat-icon"><x-icon name="vault" /></span>
            </div>
            <div>
                <div class="stat-value">Rp {{ number_format((float) $uangDiLaci, 0, ',', '.') }}</div>
                <div class="stat-note">Modal + setoran − penarikan</div>
            </div>
        </div>
        <div class="card stat stat-hi">
            <div class="stat-top">
                <span class="stat-label">Status Petugas</span>
                <span class="stat-icon"><x-icon name="user" /></span>
            </div>
            <div>
                <div class="stat-value">Aktif</div>
                <div class="stat-note">{{ auth()->user()->name }}</div>
            </div>
        </div>
    </div>

    @if($openingTransfer)
        <section class="card section">
            <div class="section-head">
                <div>
                    <div class="section-title">Konfirmasi Penerimaan Modal</div>
                    <div class="section-sub">Supervisor telah menyerahkan modal operasional. Periksa jumlahnya sebelum dikonfirmasi.</div>
                </div>
                <span class="badge badge-warn">Menunggu konfirmasi</span>
            </div>
            <div class="card-body" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px">
                <div class="panel" style="flex:1 1 320px;max-width:420px">
                    <div class="panel-head"><span>Modal diserahkan</span><x-icon name="coins" /></div>
                    <div class="panel-body">
                        <div class="kv"><span class="k">Jumlah</span><span class="v">Rp {{ number_format((float) $openingTransfer->amount, 0, ',', '.') }}</span></div>
                        <div class="kv"><span class="k">Tanggal</span><span class="v">{{ \Carbon\Carbon::parse($openingTransfer->transfer_date)->format('d/m/Y') }}</span></div>
                        <div class="kv"><span class="k">Keterangan</span><span class="v">{{ $openingTransfer->description ?? '-' }}</span></div>
                    </div>
                </div>
                <form action="{{ route('teller.kas.konfirmasi-modal', $openingTransfer->id) }}" method="POST"
                      onsubmit="return confirm('Apakah Anda benar-benar sudah menerima modal tersebut?')">
                    @csrf
                    <button type="submit" class="btn btn-primary"><x-icon name="check" /> Konfirmasi Penerimaan Modal</button>
                </form>
            </div>
        </section>
    @endif

    <div class="section split">
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Pengembalian Kas ke Brankas</div>
                    <div class="section-sub">Masukkan jumlah uang yang benar-benar diserahkan kepada Supervisor.</div>
                </div>
            </div>

            @if($closingTransfer && $closingTransfer->status === 'pending')
                <div class="card-body">
                    <div class="alert alert-warn" style="margin:0">
                        <x-icon name="clock" />
                        <span>Pengembalian <b>Rp {{ number_format((float) $closingTransfer->amount, 0, ',', '.') }}</b> sudah diajukan dan menunggu konfirmasi Supervisor.</span>
                    </div>
                </div>
            @elseif($closingTransfer && $closingTransfer->status === 'confirmed')
                <div class="card-body">
                    <div class="alert alert-success" style="margin:0">
                        <x-icon name="check" />
                        <span>Kas <b>Rp {{ number_format((float) $closingTransfer->amount, 0, ',', '.') }}</b> hari ini sudah dikembalikan dan dikonfirmasi Supervisor.</span>
                    </div>
                </div>
            @else
                <form action="{{ route('teller.kas.serahkan-kembali') }}" method="POST"
                      onsubmit="return confirm('Pastikan jumlah uang yang diserahkan sudah benar. Ajukan pengembalian kas?')">
                    @csrf
                    <div class="card-body stack">
                        <div class="panel">
                            <div class="panel-head"><span>Kas tersedia di laci</span><x-icon name="vault" /></div>
                            <div class="panel-body">
                                <div class="kv-total" style="margin:0;padding:0;border:0;font-size:16px">
                                    <span>Perkiraan</span><b>Rp {{ number_format((float) $uangDiLaci, 0, ',', '.') }}</b>
                                </div>
                                <p class="helper" style="margin-top:4px">Jumlah pengembalian tidak boleh melebihi kas yang tersedia.</p>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="field">
                                <label for="closing_amount">Jumlah kas dikembalikan (Rp)</label>
                                <input type="number" id="closing_amount" name="amount" min="1"
                                       max="{{ max(0, (float) $uangDiLaci) }}" step="1000"
                                       value="{{ old('amount') }}" placeholder="Masukkan jumlah kas" required
                                       {{ (float) $uangDiLaci <= 0 ? 'disabled' : '' }}>
                            </div>
                            <div class="field">
                                <label for="closing_description">Keterangan</label>
                                <input type="text" id="closing_description" name="description" maxlength="500"
                                       value="{{ old('description') }}" placeholder="Contoh: pengembalian kas operasional hari ini"
                                       {{ (float) $uangDiLaci <= 0 ? 'disabled' : '' }}>
                            </div>
                        </div>

                        @if((float) $uangDiLaci <= 0)
                            <p class="helper">Belum ada kas yang tersedia untuk dikembalikan.</p>
                        @endif
                    </div>
                    <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                        <button type="submit" class="btn btn-primary btn-sm" {{ (float) $uangDiLaci <= 0 ? 'disabled' : '' }}>
                            <x-icon name="send" /> Serahkan Kembali Kas
                        </button>
                    </div>
                </form>
            @endif
        </section>

        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <span class="card-title">Pekerjaan Teller</span>
                </div>
                <div class="quick-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                    <a class="quick" href="{{ route('teller.transaksi.create', ['type' => 'deposit']) }}">
                        <span class="quick-icon"><x-icon name="plus" /></span> Catat Setoran
                    </a>
                    <a class="quick" href="{{ route('teller.transaksi.create', ['type' => 'withdrawal']) }}">
                        <span class="quick-icon"><x-icon name="minus" /></span> Catat Penarikan
                    </a>
                    <a class="quick" href="{{ route('teller.koreksi') }}">
                        <span class="quick-icon"><x-icon name="history" /></span> Riwayat &amp; Koreksi
                    </a>
                    <a class="quick" href="{{ route('teller.laporan') }}">
                        <span class="quick-icon"><x-icon name="chart" /></span> Laporan Harian
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <span class="card-title">Cari Rekening Nasabah</span>
                    <a class="more" href="{{ route('teller.transaksi.create') }}">Buka transaksi</a>
                </div>
                <form action="{{ route('teller.transaksi.create') }}" method="GET" class="toolbar">
                    <div class="field" style="flex:1 1 160px;max-width:none">
                        <label for="cari_rekening">Nomor rekening</label>
                        <input id="cari_rekening" type="search" name="account_number" placeholder="Nomor rekening nasabah" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><x-icon name="search" /> Cari</button>
                </form>
                <p class="helper" style="padding:0 12px 10px">QR identitas dapat dipindai langsung dari halaman transaksi.</p>
            </div>
        </div>
    </div>
@endsection

