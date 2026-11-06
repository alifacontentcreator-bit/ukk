@extends('layouts.teller')

@section('title', 'Laporan Harian')
@section('page-title', 'Laporan Kas Harian')
@section('page-subtitle', 'Catat kas akhir dan kirim laporan transaksi harian kepada Supervisor.')

@section('page-actions')
    <form method="GET" action="{{ route('teller.laporan') }}" class="actions no-print">
        <div class="field">
            <label for="laporan_tanggal">Tanggal laporan</label>
            <select id="laporan_tanggal" name="tanggal" onchange="this.form.submit()">
                <option value="{{ today()->toDateString() }}" @selected($tanggal === today()->toDateString())>{{ today()->format('d/m/Y') }} (hari ini)</option>
                @foreach($editableReports as $draft)
                    @if(\Carbon\Carbon::parse($draft->report_date)->toDateString() !== today()->toDateString())
                        <option value="{{ \Carbon\Carbon::parse($draft->report_date)->toDateString() }}" @selected($tanggal === \Carbon\Carbon::parse($draft->report_date)->toDateString())>{{ \Carbon\Carbon::parse($draft->report_date)->format('d/m/Y') }} ({{ $draft->status === 'rejected' ? 'ditolak, perlu direvisi' : 'perlu diperbaiki' }})</option>
                    @endif
                @endforeach
            </select>
        </div>
    </form>
    <a class="btn btn-soft btn-sm no-print" href="{{ route('teller.koreksi') }}"><x-icon name="history" /> Riwayat transaksi</a>
    @if($reportHariIni)
        <a class="btn btn-soft btn-sm no-print" href="{{ route('teller.laporan.cetak', $reportHariIni->id) }}" target="_blank" rel="noopener"><x-icon name="printer" /> Cetak Rekap</a>
    @else
        <button class="btn btn-soft btn-sm no-print" type="button" onclick="window.print()"><x-icon name="printer" /> Cetak</button>
    @endif
@endsection

@section('content')
@php
    $kasAwal = (float) ($reportHariIni->saldo_awal ?? 0);
    $pengembalian = (float) ($pengembalian ?? 0);
    $mutasiBersih = (float) $setoran - (float) $penarikan - $pengembalian;
    $kasAkhirSistem = $kasAwal + $mutasiBersih;
@endphp

    @if($unreadReportCount > 0)
        <div class="alert alert-danger section" role="status">
            <x-icon name="alert" />
            <span>
                Ada {{ $unreadReportCount }} notifikasi baru dari Supervisor tentang laporan harian.
                <a href="{{ route('teller.notifikasi') }}">Lihat notifikasi</a>
            </span>
        </div>
    @endif

    @if($tanggal === today()->toDateString() || in_array($reportHariIni?->status, ['draft', 'rejected'], true))
        <section class="panel no-print">
            <div class="panel-head"><span>Status Laporan Harian</span><x-icon name="flag" /></div>
            <div class="panel-body" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:center">
                <div>
                    @if(!$reportHariIni && $tanggal === today()->toDateString())
                        <div class="name" style="font-size:13px">Belum dibuat</div>
                        <p class="helper">Buat laporan untuk mulai merekap kas harian.</p>
                    @elseif($reportHariIni->status == 'draft')
                        <div class="name" style="font-size:13px;color:var(--warn)">Draf tersimpan</div>
                        <p class="helper">Periksa kas akhir fisik, lalu kirim laporan kepada Supervisor.</p>
                    @elseif($reportHariIni->status == 'rejected')
                        <div class="name" style="font-size:13px;color:var(--bad)">Laporan ditolak Supervisor</div>
                        <p class="helper">{{ $reportHariIni->rejected_reason ?: 'Laporan perlu direvisi agar kas fisik sesuai dengan kas sistem.' }}</p>
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
                    @elseif($reportHariIni->status == 'rejected')
                        <form method="POST" action="{{ route('teller.laporan-harian.revisi', $reportHariIni->id) }}"
                              onsubmit="return confirm('Buka laporan ini untuk direvisi? Hitung ulang uang fisik kas akhir lalu kirim ulang ke Supervisor.')">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit"><x-icon name="refresh" /> Revisi Laporan Harian</button>
                        </form>
                    @endif
                </div>
            </div>

            @if($reportHariIni && $reportHariIni->status == 'draft')
                <form method="POST" action="{{ route('teller.laporan-harian.kirim') }}"
                      onsubmit="return confirm('Pastikan jumlah uang fisik kas akhir sudah dihitung dengan benar.')">
                    @csrf
                                        <input type="hidden" name="tanggal" value="{{ $tanggal }}">
                    <div class="card-body" style="border-top:1px solid var(--line-2)">
                        <span class="section-label">Rincian lembaran uang fisik kas akhir</span>
                        <div class="form-grid-3" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                            @foreach(\App\Models\DailyReport::PECAHAN_FISIK as $pecahan)
                                <div class="field">
                                    <label for="{{ $pecahan['kolom'] }}">Rp {{ number_format($pecahan['nominal'], 0, ',', '.') }}</label>
                                    <input id="{{ $pecahan['kolom'] }}" type="number" name="{{ $pecahan['kolom'] }}"
                                           value="{{ old($pecahan['kolom'], $reportHariIni->{$pecahan['kolom']} ?? 0) }}"
                                           min="0" required>
                                </div>
                            @endforeach
                        </div>

                        <span class="section-label" style="margin-top:14px">Rincian lembaran saldo awal (opsional)</span>
                        <p class="helper" style="margin:0 0 8px">Hitung lembaran modal yang kamu terima. Bila dikosongkan, cetakan memakai total modal sistem.</p>
                        <div class="form-grid-3" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                            @foreach(\App\Models\DailyReport::PECAHAN_AWAL as $pecahan)
                                <div class="field">
                                    <label for="{{ $pecahan['kolom'] }}">Rp {{ number_format($pecahan['nominal'], 0, ',', '.') }}</label>
                                    <input id="{{ $pecahan['kolom'] }}" type="number" name="{{ $pecahan['kolom'] }}"
                                           value="{{ old($pecahan['kolom'], $reportHariIni->{$pecahan['kolom']} ?? 0) }}"
                                           min="0">
                                </div>
                            @endforeach
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
