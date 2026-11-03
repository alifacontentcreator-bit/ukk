@extends('layouts.admin')

@section('title', 'Dasbor Administrator')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dasbor Utama</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Dasbor</b></div>
        </div>
        <div class="page-actions">
            <a class="btn btn-soft btn-sm" href="{{ route('admin.audit-log') }}"><x-icon name="shield" /> Log &amp; Audit</a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Teller</span>
                <span class="stat-icon"><x-icon name="users" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalTeller ?? 0, 0, ',', '.') }}</div>
                <div class="stat-note">Aktif bertugas</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Supervisor</span>
                <span class="stat-icon"><x-icon name="check" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalSupervisor ?? 0, 0, ',', '.') }}</div>
                <div class="stat-note">Siap melakukan persetujuan</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Rekening Terdaftar</span>
                <span class="stat-icon"><x-icon name="card" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalRekening ?? 0, 0, ',', '.') }}</div>
                <div class="stat-note">Data rekening aktif</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Rekening Diblokir</span>
                <span class="stat-icon"><x-icon name="lock" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($rekeningBlokir ?? 0, 0, ',', '.') }}</div>
                <div class="stat-note" style="color:var(--bad)">Perlu tindak lanjut</div>
            </div>
        </div>
    </div>

    <div class="section split">
        <section class="card">
            <div class="section-head">
                <div>
                    <div class="section-title">Aktivitas Administrasi &amp; Keamanan</div>
                    <div class="section-sub">Ringkasan aktivitas terbaru pada sistem.</div>
                </div>
                <a class="more" href="{{ route('admin.audit-log') }}">Lihat semua log</a>
            </div>
            <div class="vaulty-table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Aktivitas</th>
                            <th>Pengguna</th>
                            <th>Waktu</th>
                            <th class="num">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Masuk sebagai Administrator</td>
                            <td class="nw">{{ auth()->user()->name ?? 'Administrator' }}</td>
                            <td class="nw muted">{{ now()->format('d/m/Y H:i') }}</td>
                            <td class="num"><span class="badge badge-ok">Aman</span></td>
                        </tr>
                        <tr>
                            <td>Pengecekan integritas basis data</td>
                            <td class="nw">Sistem (otomatis)</td>
                            <td class="nw muted">{{ now()->format('d/m/Y H:i') }}</td>
                            <td class="num"><span class="badge badge-ok">Berhasil</span></td>
                        </tr>
                        <tr>
                            <td>Pembaruan limit transaksi loket</td>
                            <td class="nw">Administrator</td>
                            <td class="nw muted">{{ now()->format('d/m/Y H:i') }}</td>
                            <td class="num"><span class="badge badge-ok">Berhasil</span></td>
                        </tr>
                        <tr>
                            <td>Pencadangan buku kas harian</td>
                            <td class="nw">Sistem (otomatis)</td>
                            <td class="nw muted">{{ now()->format('d/m/Y H:i') }}</td>
                            <td class="num"><span class="badge badge-ok">Aman</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="stack">
            <div class="card">
                <div class="card-head">
                    <span class="card-title">Kontrol Cepat</span>
                </div>
                <div class="tile-grid cols-3" style="padding:10px 12px 12px">
                    <a class="tile" href="{{ route('admin.users') }}"><x-icon name="users" /><span>Pengguna</span></a>
                    <a class="tile" href="{{ route('admin.rekening') }}"><x-icon name="card" /><span>Rekening</span></a>
                    <a class="tile" href="{{ route('admin.konfigurasi') }}"><x-icon name="sliders" /><span>Konfigurasi</span></a>
                    <a class="tile" href="{{ route('admin.audit-log') }}"><x-icon name="shield" /><span>Audit</span></a>
                    <a class="tile" href="{{ route('admin.pemulihan-sandi') }}"><x-icon name="key" /><span>Reset Sandi</span></a>
                    <a class="tile" href="{{ route('admin.jurnal') }}"><x-icon name="book" /><span>Jurnal</span></a>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><span>Kesehatan Sistem</span><x-icon name="shield" /></div>
                <div class="panel-body">
                    <div class="kv"><span class="k">Basis data</span><span class="v is-ok">Terhubung</span></div>
                    <div class="kv"><span class="k">Antrean email</span><span class="v is-ok">Normal</span></div>
                    <div class="kv"><span class="k">Pencadangan terakhir</span><span class="v">{{ now()->format('d/m/Y') }}</span></div>
                    <div class="kv"><span class="k">Rekening diblokir</span><span class="v" style="color:var(--bad)">{{ number_format($rekeningBlokir ?? 0, 0, ',', '.') }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
