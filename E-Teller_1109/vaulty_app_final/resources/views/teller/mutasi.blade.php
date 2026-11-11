{{-- Tujuan file: Template Blade ini menyajikan halaman Cetak Mutasi Teller: form rekening + periode,
     pratinjau mutasi (tanggal, keterangan, debit, kredit, saldo akhir), unduhan PDF/Excel, serta
     pengelolaan permintaan mutasi yang diajukan nasabah dari aplikasi mobile. --}}
@extends('layouts.teller')

@section('title', 'Cetak Mutasi')
@section('page-title', 'Cetak Mutasi Rekening')
@section('page-subtitle', 'Riwayat transaksi nasabah: tanggal, keterangan, mutasi debit/kredit, dan saldo akhir.')

@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('teller.dashboard') }}"><x-icon name="arrow-left" /> Dasbor</a>
@endsection

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Cari Rekening &amp; Periode Mutasi</div>
                <div class="section-sub">Periode default bulan berjalan. Rentang maksimal 12 bulan.</div>
            </div>
        </div>
        <form method="GET" action="{{ route('teller.mutasi') }}" class="toolbar">
            <div class="field">
                <label for="mAccount">Nomor rekening</label>
                <input id="mAccount" type="text" name="account_number" value="{{ $filters['account_number'] }}"
                       placeholder="Contoh: TKJ-2026-01-02" required autocomplete="off">
            </div>
            <div class="field">
                <label for="mFrom">Dari tanggal</label>
                <input id="mFrom" type="date" name="from" value="{{ $filters['from'] }}" max="{{ now()->toDateString() }}">
            </div>
            <div class="field">
                <label for="mTo">Sampai tanggal</label>
                <input id="mTo" type="date" name="to" value="{{ $filters['to'] }}" max="{{ now()->toDateString() }}">
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><x-icon name="search" /> Tampilkan Mutasi</button>
            <a class="btn btn-soft btn-sm" href="{{ route('teller.mutasi') }}">Reset</a>
        </form>
    </section>

    @if($periodError)
        <div class="alert alert-danger"><x-icon name="alert" /><span>{{ $periodError }}</span></div>
    @elseif($filters['account_number'] && !$account)
        <div class="alert alert-danger">
            <x-icon name="alert" />
            <span>Nomor rekening <b>{{ $filters['account_number'] }}</b> tidak ditemukan. Periksa kembali.</span>
        </div>
    @endif

    @if($pending->isNotEmpty())
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Permintaan Mutasi dari Aplikasi Nasabah</div>
                    <div class="section-sub">Kirim berkas PDF agar nasabah menerima notifikasi, atau tolak dengan alasan yang jelas.</div>
                </div>
                <span class="badge badge-warn">{{ $pending->count() }} menunggu</span>
            </div>
            <div class="vaulty-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Diminta</th>
                            <th>Nasabah</th>
                            <th>Periode</th>
                            <th>Kode</th>
                            <th class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $requestItem)
                            @php
                                $previewQuery = [
                                    'account_number' => $requestItem->account?->account_number ?? '',
                                    'from' => $requestItem->period_from->toDateString(),
                                    'to' => $requestItem->period_to->toDateString(),
                                ];
                            @endphp
                            <tr>
                                <td class="nw">{{ $requestItem->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="name">{{ $requestItem->account?->user?->name ?? '-' }}</div>
                                    <div class="sub mono">{{ $requestItem->account?->account_number ?? '-' }}</div>
                                </td>
                                <td class="nw">{{ $requestItem->periodLabel() }}</td>
                                <td class="nw mono">{{ $requestItem->request_code }}</td>
                                <td>
                                    <div class="actions end" style="flex-wrap:wrap;gap:6px">
                                        <a class="btn btn-soft btn-sm" href="{{ route('teller.mutasi', $previewQuery) }}">
                                            <x-icon name="eye" /> Pratinjau
                                        </a>
                                        <form action="{{ route('teller.mutasi.kirim', $requestItem) }}" method="POST"
                                              onsubmit="return confirm('Buat dan kirim berkas mutasi ini ke aplikasi nasabah?')">
                                            @csrf
                                            <button class="btn btn-primary btn-sm" type="submit">
                                                <x-icon name="send" /> Kirim PDF
                                            </button>
                                        </form>
                                        <form action="{{ route('teller.mutasi.tolak', $requestItem) }}" method="POST"
                                              onsubmit="return confirm('Tolak permintaan mutasi ini?')"
                                              style="display:flex;gap:6px;align-items:center">
                                            @csrf
                                            <input name="reason" type="text" placeholder="Alasan penolakan"
                                                   required minlength="5" maxlength="500" style="min-width:170px">
                                            <button class="btn btn-danger btn-sm" type="submit">
                                                <x-icon name="x" /> Tolak
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($account && $statement)
        @php
            $downloadQuery = [
                'account_number' => $account->account_number,
                'from' => $statement['period_from_iso'],
                'to' => $statement['period_to_iso'],
            ];
            $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        @endphp
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Mutasi Rekening {{ $account->account_number }}</div>
                    <div class="section-sub">
                        {{ $account->user?->name ?? '-' }}
                        &middot; {{ $statement['period_from'] }} s/d {{ $statement['period_to'] }}
                        &middot; {{ $statement['total_transactions'] }} transaksi
                    </div>
                </div>
                <div class="actions">
                    <a class="btn btn-primary btn-sm" href="{{ route('teller.mutasi.pdf', $downloadQuery) }}"
                       target="_blank" rel="noopener"><x-icon name="file" /> PDF</a>
                    <a class="btn btn-soft btn-sm" href="{{ route('teller.mutasi.excel', $downloadQuery) }}">
                        <x-icon name="down" /> Excel
                    </a>
                    <a class="btn btn-soft btn-sm" href="{{ route('teller.mutasi.cetak', $downloadQuery) }}"
                       target="_blank" rel="noopener"><x-icon name="printer" /> Cetak</a>
                </div>
            </div>

            <div class="vaulty-table-wrap" style="border-bottom:1px solid var(--line-2)">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Saldo awal</th>
                            <th class="num">Mutasi kredit (CR)</th>
                            <th class="num">Mutasi debit (DB)</th>
                            <th class="num">Saldo akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="amount">{{ $rp($statement['opening_balance']) }}</td>
                            <td class="amount num" style="color:var(--ok)">{{ $rp($statement['total_credit']) }}</td>
                            <td class="amount num" style="color:var(--warn)">{{ $rp($statement['total_debit']) }}</td>
                            <td class="amount num"><b>{{ $rp($statement['closing_balance']) }}</b></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="vaulty-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal transaksi</th>
                            <th>Keterangan / deskripsi</th>
                            <th>Referensi</th>
                            <th class="num">Mutasi debit (DB)</th>
                            <th class="num">Mutasi kredit (CR)</th>
                            <th class="num">Saldo akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statement['rows'] as $index => $row)
                            <tr>
                                <td class="nw">{{ $index + 1 }}</td>
                                <td class="nw">{{ $row['date'] }} {{ $row['time'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td class="nw mono">{{ $row['reference'] }}</td>
                                <td class="amount num" style="color:var(--warn)">
                                    {{ $row['debit'] > 0 ? $rp($row['debit']) : '—' }}
                                </td>
                                <td class="amount num" style="color:var(--ok)">
                                    {{ $row['credit'] > 0 ? $rp($row['credit']) : '—' }}
                                </td>
                                <td class="amount num">{{ $rp($row['balance']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-cell">Tidak ada transaksi pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($history->total() > 0)
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Riwayat Permintaan Mutasi</div>
                    <div class="section-sub">Permintaan yang sudah diproses petugas (terkirim, ditolak, atau kedaluwarsa).</div>
                </div>
                <span class="badge badge-outline">{{ $history->total() }}</span>
            </div>
            <div class="vaulty-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Diproses</th>
                            <th>Nasabah</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Petugas</th>
                            <th class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $item)
                            @php
                                $itemQuery = [
                                    'account_number' => $item->account?->account_number ?? '',
                                    'from' => $item->period_from->toDateString(),
                                    'to' => $item->period_to->toDateString(),
                                ];
                                $statusBadge = match ($item->status) {
                                    \App\Models\StatementRequest::STATUS_READY => 'badge-ok',
                                    \App\Models\StatementRequest::STATUS_REJECTED => 'badge-danger',
                                    default => 'badge-outline',
                                };
                            @endphp
                            <tr>
                                <td class="nw">
                                    {{ ($item->processed_at ?? $item->created_at)->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <div class="name">{{ $item->account?->user?->name ?? '-' }}</div>
                                    <div class="sub mono">{{ $item->account?->account_number ?? '-' }}</div>
                                </td>
                                <td class="nw">{{ $item->periodLabel() }}</td>
                                <td>
                                    <span class="badge {{ $statusBadge }}">{{ $item->statusLabel() }}</span>
                                    @if($item->note)
                                        <div class="sub">{{ $item->note }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->processor?->name ?? '-' }}</td>
                                <td>
                                    <div class="actions end">
                                        <a class="more" href="{{ route('teller.mutasi', $itemQuery) }}">Pratinjau</a>
                                        @if($item->isReady())
                                            <a class="more" href="{{ route('teller.mutasi.berkas', $item) }}"
                                               target="_blank" rel="noopener">Berkas PDF</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pager :paginator="$history" />
        </section>
    @endif
@endsection
