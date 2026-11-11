{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman history, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.app')

@section('title', 'Riwayat Tabungan Digital')
@section('page-title', 'Riwayat Transaksi')
@section('page-subtitle', 'Daftar transaksi rekening Anda, lengkap dengan bukti digital.')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <div class="section-title">Riwayat Transaksi Rekening</div>
                <div class="section-sub">{{ $transactions->total() }} transaksi tersimpan.</div>
            </div>
            <span class="badge badge-outline">{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }}</span>
        </div>
        <form method="GET" action="{{ route('customer.history') }}" class="toolbar">
            <div class="field">
                <label for="hFrom">Dari tanggal</label>
                <input id="hFrom" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="field">
                <label for="hTo">Sampai tanggal</label>
                <input id="hTo" type="date" name="to" value="{{ request('to') }}">
            </div>
            <button class="btn btn-primary btn-sm" type="submit">Terapkan</button>
            <a class="btn btn-soft btn-sm" href="{{ route('customer.history') }}">Reset</a>
        </form>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Referensi</th>
                        <th>Jenis</th>
                        <th class="num">Masuk / keluar</th>
                        <th class="num">Saldo sesudah</th>
                        <th class="num">Bukti</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td class="nw">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                            <td class="nw mono">{{ $tx->reference_number }}</td>
                            <td><span class="badge {{ $tx->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $tx->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                            <td class="amount" style="color:{{ $tx->type === 'deposit' ? 'var(--ok)' : 'var(--warn)' }}">
                                {{ $tx->type === 'deposit' ? '+' : '−' }} Rp {{ number_format($tx->amount, 0, ',', '.') }}
                            </td>
                            <td class="amount">Rp {{ number_format(data_get($tx->data_after, 'balance', 0), 0, ',', '.') }}</td>
                            <td class="num"><a class="more" href="{{ route('customer.receipt', $tx) }}">Bukti digital</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Belum ada transaksi pada rentang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$transactions" />
    </section>
@endsection
