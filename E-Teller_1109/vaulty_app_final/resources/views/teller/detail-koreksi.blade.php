{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman detail koreksi, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.teller')

@section('title', 'Detail Koreksi')
@section('page-title', 'Detail Pengajuan Koreksi')
@section('page-subtitle', 'Informasi lengkap pengajuan koreksi transaksi.')

@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('teller.koreksi') }}"><x-icon name="arrow-left" /> Kembali ke Koreksi</a>
@endsection

@section('content')
    @php
        $statusMap = [
            'pending' => ['badge-warn', 'Menunggu Supervisor'],
            'approved' => ['badge-ok', 'Disetujui'],
            'rejected' => ['badge-danger', 'Ditolak'],
        ];
        [$statusClass, $statusLabel] = $statusMap[$correction->status] ?? ['badge-outline', ucfirst($correction->status)];
    @endphp

    <div class="alert alert-info">
        <x-icon name="info" />
        <div>
            <b>{{ $statusLabel }}.</b>
            @if($correction->status === 'pending')
                Pengajuan masih menunggu pemeriksaan dan keputusan Supervisor.
            @elseif($correction->status === 'approved')
                Pengajuan telah disetujui Supervisor dan data transaksi sudah disesuaikan.
            @elseif($correction->status === 'rejected')
                Pengajuan ditolak Supervisor; transaksi tidak berubah.
            @else
                Status pengajuan belum dikenali sistem.
            @endif
        </div>
    </div>

    <div class="section split">
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Informasi Transaksi</div>
                    <div class="section-sub">Data transaksi yang diajukan untuk dikoreksi.</div>
                </div>
                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            <div class="card-body">
                @if($transaction)
                    <div class="kv"><span class="k">Nomor referensi</span><span class="v mono">{{ $transaction->reference_number }}</span></div>
                    <div class="kv">
                        <span class="k">Jenis transaksi</span>
                        <span class="v">{{ $transaction->type === 'deposit' ? 'Setoran' : ($transaction->type === 'withdrawal' ? 'Penarikan' : ucfirst($transaction->type)) }}</span>
                    </div>
                    <div class="kv"><span class="k">Nominal</span><span class="v">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</span></div>
                    <div class="kv">
                        <span class="k">Status transaksi</span>
                        <span class="v"><span class="badge {{ $transaction->status === 'approved' ? 'badge-ok' : ($transaction->status === 'rejected' ? 'badge-danger' : 'badge-warn') }}">{{ ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'pending' => 'Menunggu'][$transaction->status] ?? ucfirst($transaction->status) }}</span></span>
                    </div>
                    <div class="kv"><span class="k">Dibuat</span><span class="v mono">{{ $transaction->created_at->format('d/m/Y H:i:s') }}</span></div>
                    <div class="kv"><span class="k">ID transaksi</span><span class="v mono">#{{ $transaction->id }}</span></div>
                @else
                    <p class="empty-inline">Data transaksi tidak ditemukan.</p>
                @endif
            </div>
        </section>

        <div class="stack">
            <section class="card">
                <div class="card-head">
                    <span class="card-title">Alasan Koreksi</span>
                    <span class="badge badge-outline">{{ $correction->created_at->format('d/m/Y') }}</span>
                </div>
                <div class="card-body">
                    <p class="helper" style="white-space:pre-line">{{ $correction->reason ?: 'Teller tidak menuliskan alasan koreksi.' }}</p>
                </div>
            </section>

            <div class="panel">
                <div class="panel-head"><span>Informasi Pengajuan</span><x-icon name="history" /></div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Teller pengaju</span><span class="v">{{ $teller->name ?? auth()->user()->name }}</span></div>
                    <div class="kv"><span class="k">ID teller</span><span class="v mono">{{ $correction->teller_id }}</span></div>
                    <div class="kv"><span class="k">Tanggal pengajuan</span><span class="v mono">{{ $correction->created_at->format('d/m/Y H:i:s') }}</span></div>
                    <div class="kv"><span class="k">Supervisor</span><span class="v">{{ $supervisor->name ?? 'Belum diproses' }}</span></div>
                    <div class="kv"><span class="k">Pembaruan terakhir</span><span class="v mono">{{ $correction->updated_at->format('d/m/Y H:i:s') }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
