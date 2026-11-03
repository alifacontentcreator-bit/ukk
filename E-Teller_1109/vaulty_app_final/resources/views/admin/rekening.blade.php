@extends('layouts.admin')

@section('title', 'Master Rekening')

@section('content')
    <div class="page-head">
        <div>
            <h1>Master Rekening</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Master Rekening</b></div>
        </div>
    </div>

    <div class="grid-3">
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Rekening</span>
                <span class="stat-icon"><x-icon name="card" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($accounts->count(), 0, ',', '.') }}</div>
                <div class="stat-note">Rekening terdaftar</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Rekening Aktif</span>
                <span class="stat-icon"><x-icon name="check" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($accounts->count(), 0, ',', '.') }}</div>
                <div class="stat-note">Dapat digunakan</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Nonaktif</span>
                <span class="stat-icon"><x-icon name="lock" /></span>
            </div>
            <div>
                <div class="stat-value">0</div>
                <div class="stat-note">Tidak dapat digunakan</div>
            </div>
        </div>
    </div>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Daftar Rekening</div>
                <div class="section-sub">Data rekening nasabah yang dikelola Administrator.</div>
            </div>
            <span class="badge badge-outline">{{ $accounts->count() }} rekening</span>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Rekening</th>
                        <th>NIS</th>
                        <th>Nasabah</th>
                        <th class="num">Saldo</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td class="nw mono">{{ $account->account_number }}</td>
                            <td class="nw muted">{{ $account->user->nis ?? '-' }}</td>
                            <td>
                                <div class="person">
                                    @if($account->user?->photo)
                                        <img class="et-avatar et-avatar-photo" src="{{ asset('storage/' . $account->user->photo) }}" alt="Foto {{ $account->user->name }}">
                                    @else
                                        <span class="et-avatar">{{ strtoupper(substr($account->user->name ?? 'U', 0, 1)) }}</span>
                                    @endif
                                    <span>
                                        <span class="name">{{ $account->user->name ?? '-' }}</span>
                                        <span class="sub">{{ $account->user->kelas ?: 'Nasabah' }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="amount">Rp {{ number_format($account->balance, 0, ',', '.') }}</td>
                            <td><span class="badge badge-ok">Aktif</span></td>
                            <td class="nw muted">{{ $account->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="actions end">
                                    <a class="icon-btn" href="{{ route('admin.rekening.qr', $account) }}" title="QR rekening {{ $account->account_number }}" aria-label="QR rekening {{ $account->account_number }}"><x-icon name="qr" /></a>
                                    <a class="icon-btn" href="{{ route('admin.rekening.edit', $account) }}" title="Rincian rekening {{ $account->account_number }}" aria-label="Rincian rekening {{ $account->account_number }}"><x-icon name="eye" /></a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">Belum ada data rekening terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
