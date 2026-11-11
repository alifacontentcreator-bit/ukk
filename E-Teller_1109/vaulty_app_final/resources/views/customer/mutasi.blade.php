{{-- Tujuan file: Template Blade ini menyajikan halaman Mutasi nasabah: mengajukan cetak mutasi,
     memantau status permintaan, dan mengunduh berkas PDF yang sudah disiapkan petugas. --}}
@extends('layouts.app')

@section('title', 'Mutasi Rekening')
@section('page-title', 'Mutasi Rekening')
@section('page-subtitle', 'Ajukan cetak mutasi, lalu unduh berkas PDF setelah petugas mengirimkannya.')

@section('content')
    <div class="split">
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Ajukan Cetak Mutasi</div>
                    <div class="section-sub">Pilih periode mutasi yang Anda butuhkan (maksimal 12 bulan).</div>
                </div>
                <span class="badge badge-outline">PDF</span>
            </div>
            <form method="POST" action="{{ route('customer.mutasi.ajukan') }}" class="toolbar">
                @csrf
                <div class="field">
                    <label for="sFrom">Dari tanggal</label>
                    <input id="sFrom" type="date" name="period_from" value="{{ old('period_from', now()->startOfMonth()->toDateString()) }}"
                           max="{{ now()->toDateString() }}" required>
                </div>
                <div class="field">
                    <label for="sTo">Sampai tanggal</label>
                    <input id="sTo" type="date" name="period_to" value="{{ old('period_to', now()->toDateString()) }}"
                           max="{{ now()->toDateString() }}" required>
                </div>
                <div class="field" style="flex:1;min-width:220px">
                    <label for="sReason">Keperluan (opsional)</label>
                    <input id="sReason" type="text" name="reason" value="{{ old('reason') }}" maxlength="500"
                           placeholder="Contoh: verifikasi beasiswa">
                </div>
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="send" /> Ajukan Mutasi</button>
            </form>
            <p class="section-sub" style="padding:0 12px 12px">
                Berkas disiapkan oleh petugas. Anda akan menerima notifikasi di aplikasi saat berkas PDF siap diunduh.
            </p>
        </section>

        <div class="panel">
            <div class="panel-head"><span>Data Rekening</span><x-icon name="card" /></div>
            <div class="panel-body">
                <div class="kv"><span class="k">No. rekening</span><span class="v mono">{{ $account->account_number }}</span></div>
                <div class="kv"><span class="k">Nama</span><span class="v">{{ auth()->user()->name }}</span></div>
                <div class="kv"><span class="k">Saldo saat ini</span><span class="v">Rp {{ number_format($account->balance, 0, ',', '.') }}</span></div>
            </div>
        </div>
    </div>

    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Riwayat Permintaan Mutasi</div>
                <div class="section-sub">{{ $requests->total() }} permintaan tercatat.</div>
            </div>
            <span class="badge badge-outline">{{ $requests->total() }}</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Diminta</th>
                        <th>Periode</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th class="num">Berkas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $statementRequest)
                        @php
                            $statusBadge = match ($statementRequest->status) {
                                \App\Models\StatementRequest::STATUS_READY => 'badge-ok',
                                \App\Models\StatementRequest::STATUS_REJECTED => 'badge-danger',
                                \App\Models\StatementRequest::STATUS_EXPIRED => 'badge-warn',
                                default => 'badge-outline',
                            };
                        @endphp
                        <tr>
                            <td class="nw">{{ $statementRequest->created_at->format('d/m/Y H:i') }}</td>
                            <td class="nw">{{ $statementRequest->periodLabel() }}</td>
                            <td class="nw mono">{{ $statementRequest->request_code }}</td>
                            <td><span class="badge {{ $statusBadge }}">{{ $statementRequest->statusLabel() }}</span></td>
                            <td>{{ $statementRequest->note ?: '—' }}</td>
                            <td class="num">
                                @if($statementRequest->isReady())
                                    <div class="actions">
                                        <a class="btn btn-soft btn-sm" href="{{ route('customer.mutasi.pdf', $statementRequest) }}">
                                            <x-icon name="down" /> Unduh PDF
                                        </a>
                                        <a class="btn btn-soft btn-sm" href="{{ route('customer.mutasi.excel', $statementRequest) }}">
                                            <x-icon name="down" /> Excel
                                        </a>
                                    </div>
                                @elseif($statementRequest->isPending())
                                    <span class="sub">Menunggu petugas</span>
                                @else
                                    <span class="sub">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Belum ada permintaan mutasi. Ajukan melalui form di atas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$requests" />
    </section>
@endsection
