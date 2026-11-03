@extends('layouts.app')

@section('title', 'Periksa Saldo')
@section('page-title', 'Saldo Rekening')
@section('page-subtitle', 'Saldo terkini rekening tabungan Anda.')

@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('customer.history') }}"><x-icon name="history" /> Riwayat transaksi</a>
@endsection

@section('content')
    <section class="card" style="max-width:560px">
        <div class="section-head">
            <div>
                <div class="section-title">Saldo Rekening</div>
                <div class="section-sub">Nomor rekening {{ $account->account_number }}</div>
            </div>
            <span class="badge {{ $account->status === 'active' ? 'badge-ok' : 'badge-danger' }}">{{ $account->status === 'active' ? 'Aktif' : 'Diblokir' }}</span>
        </div>
        <div class="card-body">
            <div class="panel">
                <div class="panel-head"><span>Saldo tersedia</span><x-icon name="wallet" /></div>
                <div class="panel-body" style="padding:14px 12px">
                    <div style="font-size:26px;font-weight:700;letter-spacing:-.01em;color:var(--green-dark);font-variant-numeric:tabular-nums">
                        Rp {{ number_format($account->balance, 0, ',', '.') }}
                    </div>
                    <p class="helper" style="margin-top:2px">Per {{ now()->format('d/m/Y H:i') }} WIB</p>
                </div>
            </div>

            <div class="stack" style="margin-top:10px">
                <div class="kv"><span class="k">Nama nasabah</span><span class="v">{{ auth()->user()->name }}</span></div>
                <div class="kv"><span class="k">Kelas</span><span class="v">{{ auth()->user()->kelas ?? '-' }}</span></div>
                <div class="kv"><span class="k">Jenis tabungan</span><span class="v">{{ ($account->product_type ?? null) === 'tabungan_karyawan' ? 'Guru/Karyawan' : 'Siswa' }}</span></div>
            </div>
        </div>
        <div class="form-actions" style="padding:0 12px 12px;margin:0;border-top:0">
            <a class="btn btn-primary btn-sm" href="{{ route('customer.history') }}">Lihat Riwayat Transaksi</a>
        </div>
    </section>
@endsection
