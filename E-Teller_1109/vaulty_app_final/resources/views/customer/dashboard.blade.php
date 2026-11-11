{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman dashboard, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.app')

@section('title', 'Dasbor Nasabah')
@section('page-title', 'Ringkasan Rekening')
@section('page-subtitle', 'Saldo, transaksi terbaru, dan pemberitahuan rekening Anda.')

@section('topbar-actions')
    <a class="topbar-icon-btn" href="{{ route('customer.notifications') }}"
       title="Notifikasi ({{ $unread }} belum dibaca)" aria-label="Notifikasi, {{ $unread }} belum dibaca">
        <x-icon name="bell" />
        @if($unread > 0)<span class="dot">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </a>
@endsection

@section('content')
    <div class="grid-4">
        <div class="card stat stat-hi">
            <div class="stat-top">
                <span class="stat-label">Saldo Rekening</span>
                <span class="stat-icon"><x-icon name="wallet" /></span>
            </div>
            <div>
                <div class="stat-value">Rp {{ number_format($account->balance, 0, ',', '.') }}</div>
                <div class="stat-note">Nomor rekening {{ $account->account_number }}</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Status Rekening</span>
                <span class="stat-icon"><x-icon name="check" /></span>
            </div>
            <div>
                <div class="stat-value">{{ $account->status === 'active' ? 'Aktif' : 'Diblokir' }}</div>
                <div class="stat-note">Rekening tabungan Anda</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Transaksi Terbaru</span>
                <span class="stat-icon"><x-icon name="history" /></span>
            </div>
            <div>
                <div class="stat-value">{{ $recentTransactions->count() }}</div>
                <div class="stat-note">Transaksi berhasil terbaru</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Notifikasi Belum Dibaca</span>
                <span class="stat-icon"><x-icon name="bell" /></span>
            </div>
            <div>
                <div class="stat-value">{{ $unread }}</div>
                <div class="stat-note">Pemberitahuan rekening</div>
            </div>
        </div>
    </div>

    <div class="section split">
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Transaksi Terbaru</div>
                    <div class="section-sub">Riwayat transaksi yang sudah disetujui.</div>
                </div>
                <a class="more" href="{{ route('customer.history') }}">Lihat semua</a>
            </div>
            <div class="vaulty-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Referensi</th>
                            <th>Jenis</th>
                            <th class="num">Nominal</th>
                            <th>Status</th>
                            <th class="num">Bukti</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $tx)
                            <tr>
                                <td class="nw">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                                <td class="nw mono">{{ $tx->reference_number }}</td>
                                <td><span class="badge {{ $tx->type === 'deposit' ? 'badge-ok' : 'badge-warn' }}">{{ $tx->type === 'deposit' ? 'Setoran' : 'Penarikan' }}</span></td>
                                <td class="amount">Rp {{ number_format($tx->amount, 0, ',', '.') }}</td>
                                <td><span class="badge badge-ok">Berhasil</span></td>
                                <td class="num"><a class="more" href="{{ route('customer.receipt', $tx) }}">Bukti digital</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty-cell">Belum ada transaksi yang berhasil.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <span class="card-title">Layanan Nasabah</span>
                </div>
                <div class="quick-grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                    <a class="quick" href="{{ route('customer.saldo') }}"><span class="quick-icon"><x-icon name="wallet" /></span> Periksa Saldo</a>
                    <a class="quick" href="{{ route('customer.history') }}"><span class="quick-icon"><x-icon name="history" /></span> Riwayat</a>
                    <a class="quick" href="{{ route('customer.mutasi') }}"><span class="quick-icon"><x-icon name="receipt" /></span> Mutasi</a>
                    <a class="quick" href="{{ route('customer.notifications') }}"><span class="quick-icon"><x-icon name="bell" /></span> Notifikasi</a>
                    <a class="quick" href="{{ route('customer.qr-data') }}"><span class="quick-icon"><x-icon name="qr" /></span> QR Nasabah</a>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><span>Data Rekening</span><x-icon name="card" /></div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Nama</span><span class="v">{{ auth()->user()->name }}</span></div>
                    <div class="kv"><span class="k">Nomor rekening</span><span class="v mono">{{ $account->account_number }}</span></div>
                    <div class="kv"><span class="k">Kelas</span><span class="v">{{ auth()->user()->kelas ?? '-' }}</span></div>
                    <div class="kv"><span class="k">Status</span><span class="v"><span class="badge {{ $account->status === 'active' ? 'badge-ok' : 'badge-danger' }}">{{ $account->status === 'active' ? 'Aktif' : 'Diblokir' }}</span></span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
