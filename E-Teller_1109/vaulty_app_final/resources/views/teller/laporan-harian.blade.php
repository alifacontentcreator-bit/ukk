@extends('layouts.teller')

@section('title', 'Laporan Harian')
@section('page-title', 'Laporan Kas Harian')
@section('page-subtitle', 'Catat kas akhir dan kirim laporan transaksi harian kepada Supervisor.')

@section('page-actions')
    <form method="GET" action="{{ route('teller.laporan') }}" class="actions no-print">
        <div class="field">
            <label for="laporan_tanggal">Tanggal</label>
            <input id="laporan_tanggal" type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.submit()">
        </div>
    </form>
    <button class="btn btn-soft btn-sm no-print" type="button" onclick="window.print()"><x-icon name="printer" /> Cetak</button>
@endsection

@section('content')
@php
    $kasAwal = (float) ($reportHariIni->saldo_awal ?? 0);
    $pengembalian = (float) ($pengembalian ?? 0);
    $mutasiBersih = (float) $setoran - (float) $penarikan - $pengembalian;
    $kasAkhirSistem = $kasAwal + $mutasiBersih;
@endphp

    @if($tanggal == date('Y-m-d'))
        <section class="panel no-print">
            <div class="panel-head"><span>Status Laporan Harian</span><x-icon name="flag" /></div>
            <div class="panel-body" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:center">
                <div>
                    @if(!$reportHariIni)
                        <div class="name" style="font-size:13px">Belum dibuat</div>
                        <p class="helper">Buat laporan untuk mulai merekap kas harian.</p>
                    @elseif($reportHariIni->status == 'draft')
                        <div class="name" style="font-size:13px;color:var(--warn)">Draf tersimpan</div>
                        <p class="helper">Periksa kas akhir fisik, lalu kirim laporan kepada Supervisor.</p>
                    @elseif($reportHariIni->status == 'submitted')
                        <div class="name" style="font-size:13px">Menunggu verifikasi</div>
                        <p class="helper">Laporan sudah dikirim dan menunggu pemeriksaan Supervisor.</p>
                    @elseif($reportHariIni->status == 'approved')
                        <div class="name" style="font-size:13px;color:var(--ok)">Laporan disetujui</div>
                        <p class="helper">Laporan kas harian Anda telah selesai diperiksa.</p>
                    @endif
                </div>
                <div>
                    @if(!$reportHariIni)
                        <form method="POST" action="{{ route('teller.laporan-harian.draft') }}">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit"><x-icon name="save" /> Buat Laporan Harian</button>
                        </form>
                    @endif
                </div>
            </div>

            @if($reportHariIni && $reportHariIni->status == 'draft')
                <form method="POST" action="{{ route('teller.laporan-harian.kirim') }}"
                      onsubmit="return confirm('Pastikan jumlah uang fisik kas akhir sudah dihitung dengan benar.')">
                    @csrf
                    <div class="card-body" style="border-top:1px solid var(--line-2)">
                        <span class="section-label">Rincian fisik kas akhir</span>
                        <div class="form-grid-3">
                            <div class="field">
                                <label for="p100">Rp 100.000</label>
                                <input id="p100" type="number" name="pecahan_100k" value="{{ old('pecahan_100k', 0) }}" min="0" required>
                            </div>
                            <div class="field">
                                <label for="p50">Rp 50.000</label>
                                <input id="p50" type="number" name="pecahan_50k" value="{{ old('pecahan_50k', 0) }}" min="0" required>
                            </div>
                            <div class="field">
                                <label for="p20">Rp 20.000</label>
                                <input id="p20" type="number" name="pecahan_20k" value="{{ old('pecahan_20k', 0) }}" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
                        <button class="btn btn-primary btn-sm" type="submit"><x-icon name="send" /> Kirim Laporan ke Supervisor</button>
                    </div>
                </form>
            @endif
        </section>
    @endif


    <div class="grid-4 section">
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Kas Awal</span>
                <span class="stat-icon"><x-icon name="vault" /></span>
            </div>
            <div>
                <div class="stat-value">Rp {{ number_format($kasAwal, 0, ',', '.') }}</div>
                <div class="stat-note">Modal dari Supervisor</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Setoran</span>
                <span class="stat-icon"><x-icon name="down" /></span>
            </div>
            <div>
                <div class="stat-value" style="color:var(--ok)">+ Rp {{ number_format($setoran, 0, ',', '.') }}</div>
                <div class="stat-note">Uang masuk dari nasabah</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Penarikan</span>
                <span class="stat-icon"><x-icon name="up" /></span>
            </div>
            <div>
                <div class="stat-value" style="color:var(--warn)">&minus; Rp {{ number_format($penarikan, 0, ',', '.') }}</div>
                <div class="stat-note">Uang keluar untuk nasabah</div>
            </div>
        </div>
        <div class="card stat stat-hi">
            <div class="stat-top">
                <span class="stat-label">Kas Akhir Sistem</span>
                <span class="stat-icon"><x-icon name="coins" /></span>
            </div>
            <div>
                <div class="stat-value">Rp {{ number_format($kasAkhirSistem, 0, ',', '.') }}</div>
                <div class="stat-note">Kas awal + setoran &minus; penarikan &minus; pengembalian</div>
            </div>
        </div>
    </div>


    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Rincian Transaksi Teller</div>
                <div class="section-sub">{{ $summary['transaction_count'] }} transaksi pada tanggal ini.</div>
            </div>
            <span class="badge badge-outline">{{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Waktu</th>
                        <th>No. referensi</th>
                        <th>Nasabah</th>
                        <th>Jenis</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $index => $trx)
                        <tr>
                            <td class="nw muted">{{ $index + 1 }}</td>
                            <td class="nw">{{ $trx->created_at->format('H:i') }}</td>
                            <td class="nw mono">{{ $trx->transaction_code }}</td>
                            <td>{{ $trx->account->user->name ?? 'Anonim' }}</td>
                            <td><span class="badge {{ $trx->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $trx->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                            <td class="amount" style="color:{{ $trx->type === 'deposit' ? 'var(--ok)' : 'var(--warn)' }}">
                                {{ $trx->type === 'deposit' ? '+' : '−' }} Rp {{ number_format($trx->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Tidak ada transaksi pada tanggal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

@endsection
