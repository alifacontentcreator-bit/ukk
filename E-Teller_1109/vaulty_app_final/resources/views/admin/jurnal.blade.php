@extends('layouts.admin')

@section('title', 'Audit Jurnal Akuntansi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Jurnal Akuntansi Sistem</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Jurnal Akuntansi</b></div>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary btn-sm" href="{{ route('admin.jurnal.pdf', $filters) }}" target="_blank" rel="noopener"><x-icon name="printer" /> Cetak PDF</a>
        </div>
    </div>

    <form class="toolbar-inline" method="GET" action="{{ route('admin.jurnal') }}" data-auto-filter>
        <div class="field field-wide">
            <label for="kategori">Kategori nasabah</label>
            <select id="kategori" name="kategori">
                <option value="semua" @selected(($kategori ?? 'semua') === 'semua')>Semua kategori</option>
                <option value="siswa" @selected(($kategori ?? '') === 'siswa')>Tabungan siswa</option>
                <option value="guru" @selected(($kategori ?? '') === 'guru')>Guru / karyawan</option>
            </select>
        </div>
        <div class="field">
            <label for="date">Tanggal</label>
            <input id="date" type="date" name="date" value="{{ $filters['date'] ?? $filters['date_to'] ?? $filters['date_from'] ?? '' }}">
        </div>
        <div class="field">
            <label for="name">Nama nasabah</label>
            <input id="name" type="search" name="name" value="{{ $filters['name'] ?? '' }}" placeholder="Cari nama">
        </div>
        <div class="field">
            <label for="account_code">Kode akun</label>
            <input id="account_code" type="text" name="account_code" value="{{ $filters['account_code'] ?? '' }}" placeholder="mis. 101">
        </div>
        <a class="btn btn-soft btn-sm" href="{{ route('admin.jurnal') }}"><x-icon name="refresh" /> Reset</a>
    </form>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Mutasi Debit dan Kredit</div>
                <div class="section-sub">{{ $jurnals->count() }} baris jurnal &middot; pengawasan mutasi secara transparan</div>
            </div>
            <span class="badge badge-outline">{{ $jurnals->count() }} baris</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Referensi</th>
                        <th>Nasabah (kategori)</th>
                        <th>Kode akun</th>
                        <th>Posisi</th>
                        <th class="num">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jurnals as $jurnal)
                        @php
                            $user = $jurnal->transaction->account->user ?? null;
                            $isGuru = $user && $user->kelas === 'Pegawai';
                        @endphp
                        <tr>
                            <td class="nw muted mono">{{ $jurnal->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="nw mono" style="color:var(--green-dark)">{{ $jurnal->transaction->transaction_code ?? '-' }}</td>
                            <td>
                                <span class="name">{{ $user->name ?? 'Sistem / Anonim' }}</span>
                                <span class="sub" style="color:{{ $isGuru ? 'var(--warn)' : 'var(--green)' }}">{{ $isGuru ? 'Guru/Karyawan' : 'Siswa' }}</span>
                            </td>
                            <td class="nw mono">{{ $jurnal->account_code }}</td>
                            <td>
                                <span class="badge {{ $jurnal->position === 'debit' ? 'badge-ok' : 'badge-warn' }}">{{ $jurnal->position }}</span>
                            </td>
                            <td class="amount">Rp {{ number_format($jurnal->amount, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-cell">Tidak ada jurnal akuntansi untuk kategori ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
