{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman tukar shift, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.supervisor')

{{--
    HALAMAN INI UNTUK: daftar pengajuan TUKAR SHIFT dari Teller.
    - Menampilkan pengaju (Teller A), pengganti (Teller B), tanggal, shift, alasan.
    - Tombol "Setujui" → kunci transaksi pindah dari A ke B pada tanggal tersebut.
    - Tombol "Tolak"   → pengajuan ditolak + alasan.
--}}
@section('title', 'Tukar Shift')
@section('page-title', 'Tukar Shift')
@section('page-subtitle', 'Setujui pengajuan tukar shift agar teller pengganti bisa bertransaksi pada tanggal tersebut.')

@section('content')
<div class="grid-4">
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Menunggu</span><span class="stat-icon"><x-icon name="clock" /></span></div>
        <div><div class="stat-value">{{ $jumlahMenunggu }}</div><div class="stat-note">Pengajuan belum diproses</div></div>
    </div>
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">Daftar Ditampilkan</span><span class="stat-icon"><x-icon name="swap" /></span></div>
        <div><div class="stat-value">{{ $pengajuan->total() }}</div><div class="stat-note">Status: {{ ucfirst($status) }}</div></div>
    </div>
</div>

<section class="card section">
    <form method="GET" action="{{ route('supervisor.tukar-shift') }}" class="toolbar" data-auto-filter>
        <div class="field"><label for="tStatus">Status pengajuan</label>
            <select id="tStatus" name="status">
                <option value="pending" @selected($status === 'pending')>Menunggu</option>
                <option value="approved" @selected($status === 'approved')>Disetujui</option>
                <option value="rejected" @selected($status === 'rejected')>Ditolak</option>
                <option value="cancelled" @selected($status === 'cancelled')>Dibatalkan</option>
                <option value="semua" @selected($status === 'semua')>Semua</option>
            </select>
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Diajukan</th>
                    <th>Teller Izin (A)</th>
                    <th>Pengganti (B)</th>
                    <th>Tanggal Shift</th>
                    <th>Shift</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($pengajuan as $item)
                <tr>
                    <td class="nw">{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <div class="name">{{ $item->requester->name ?? '-' }}</div>
                        <div class="sub">{{ $item->requester->kelas ?? '-' }}</div>
                    </td>
                    <td>
                        <div class="name">{{ $item->substitute->name ?? '-' }}</div>
                        <div class="sub">{{ $item->substitute->kelas ?? '-' }}</div>
                    </td>
                    <td class="nw">{{ $item->duty_date->translatedFormat('l, d F Y') }}</td>
                    <td>{{ $item->shift?->label() ?? '-' }}</td>
                    <td class="sub" style="min-width:180px;white-space:normal">{{ $item->reason }}</td>
                    <td class="nw">
                        <span class="badge {{ match($item->status) {
                            'approved' => 'badge-ok',
                            'rejected' => 'badge-danger',
                            'cancelled' => 'badge-outline',
                            default => 'badge-warn',
                        } }}">{{ $item->statusLabel() }}</span>
                        @if($item->rejected_reason)<div class="sub">{{ $item->rejected_reason }}</div>@endif
                    </td>
                    <td class="nw">
                        @if($item->status === 'pending')
                            <div class="actions">
                                {{-- SETUJUI: kunci transaksi pindah dari Teller A ke Teller B. --}}
                                <form method="POST" action="{{ route('supervisor.tukar-shift.setujui', $item->id) }}"
                                      onsubmit="return confirm('Setujui tukar shift ini? Teller pengganti langsung bisa bertransaksi pada tanggal tersebut.')">
                                    @csrf
                                    <button class="btn btn-primary btn-sm" type="submit">Setujui</button>
                                </form>

                                {{-- TOLAK: wajib mengisi alasan. --}}
                                <form method="POST" action="{{ route('supervisor.tukar-shift.tolak', $item->id) }}" class="stack" style="gap:4px"
                                      onsubmit="return confirm('Tolak pengajuan tukar shift ini?')">
                                    @csrf
                                    <input type="text" name="rejected_reason" placeholder="Alasan penolakan" required maxlength="255" style="min-width:160px">
                                    <button class="btn btn-danger btn-sm" type="submit">Tolak</button>
                                </form>
                            </div>
                        @else
                            <span class="sub">{{ $item->approver->name ?? '-' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">Belum ada pengajuan tukar shift.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($pengajuan->hasPages())<x-pager :paginator="$pengajuan" />@endif
</section>
@endsection
