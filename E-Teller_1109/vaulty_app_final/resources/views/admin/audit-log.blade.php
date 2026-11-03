@extends('layouts.admin')

@section('title', 'Audit Log')

@section('content')

@php
    $logs = \Illuminate\Support\Facades\DB::table('audit_logs')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->get();

    $totalLog = $logs->count();
    $totalSuccess = $logs->where('status', 'success')->count();
    $totalFailed = $logs->where('status', 'failed')->count();
    $totalToday = $logs->filter(function ($log) {
        return \Carbon\Carbon::parse($log->created_at)->isToday();
    })->count();
@endphp

    <div class="page-head">
        <div>
            <h1>Audit Log Sistem</h1>
            <div class="crumb"><span>Administrator</span><i>/</i><b>Log &amp; Audit</b></div>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Total Log</span>
                <span class="stat-icon"><x-icon name="list" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalLog, 0, ',', '.') }}</div>
                <div class="stat-note">Seluruh aktivitas tercatat</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Hari Ini</span>
                <span class="stat-icon"><x-icon name="calendar" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalToday, 0, ',', '.') }}</div>
                <div class="stat-note">Aktivitas hari ini</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Berhasil</span>
                <span class="stat-icon"><x-icon name="check" /></span>
            </div>
            <div>
                <div class="stat-value">{{ number_format($totalSuccess, 0, ',', '.') }}</div>
                <div class="stat-note">Aktivitas berhasil</div>
            </div>
        </div>
        <div class="card stat">
            <div class="stat-top">
                <span class="stat-label">Gagal</span>
                <span class="stat-icon"><x-icon name="alert" /></span>
            </div>
            <div>
                <div class="stat-value" @if($totalFailed > 0) style="color:var(--bad)" @endif>{{ number_format($totalFailed, 0, ',', '.') }}</div>
                <div class="stat-note">Aktivitas gagal</div>
            </div>
        </div>
    </div>

    <p class="callout callout-info section">Log audit hanya dapat dilihat. Data dipakai untuk mengetahui aktivitas pengguna, modul yang digunakan, waktu aktivitas, alamat IP, dan status proses.</p>

    <section class="card section">
        <div class="section-head">
            <div>
                <div class="section-title">Riwayat Aktivitas Sistem</div>
                <div class="section-sub">Daftar aktivitas pengguna dan proses penting yang tercatat oleh sistem.</div>
            </div>
            <form method="GET" action="{{ url()->current() }}" class="toolbar" style="padding:0;gap:6px">
                <div class="field field-auto">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari aktivitas..." style="width:180px">
                </div>
                <button class="btn btn-primary btn-sm" type="submit"><x-icon name="search" /> Cari</button>
            </form>
        </div>
        <div class="vaulty-table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Peran</th>
                        <th>Aktivitas</th>
                        <th>Modul</th>
                        <th>IP address</th>
                        <th class="num">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $user = $log->user_id ? \App\Models\User::find($log->user_id) : null;

                            $search = strtolower(request('search', ''));
                            $searchable = strtolower(
                                ($user->name ?? '') . ' ' .
                                ($user->username ?? '') . ' ' .
                                ($log->action ?? '') . ' ' .
                                ($log->module ?? '') . ' ' .
                                ($log->description ?? '') . ' ' .
                                ($log->ip_address ?? '')
                            );
                        @endphp

                        @if($search === '' || str_contains($searchable, $search))
                            <tr>
                                <td class="nw">
                                    <span class="name">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y') }}</span>
                                    <span class="sub mono">{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}</span>
                                </td>
                                <td class="nw">
                                    <div class="person">
                                        <span class="et-avatar">{{ strtoupper(substr($user->name ?? 'S', 0, 1)) }}</span>
                                        <span>
                                            <span class="name">{{ $user->name ?? 'Sistem' }}</span>
                                            <span class="sub">{{ $user->username ?? 'sistem' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="nw">
                                    <span class="badge {{ match($user->role ?? '') { 'admin' => 'badge-soft', 'teller' => 'badge-ok', 'supervisor' => 'badge-soft', 'customer' => 'badge-outline', default => 'badge-outline' } }}">
                                        {{ ['admin' => 'Administrator', 'supervisor' => 'Supervisor', 'teller' => 'Teller', 'customer' => 'Nasabah'][$user->role ?? ''] ?? 'Sistem' }}
                                    </span>
                                </td>
                                <td style="min-width:220px">
                                    <span class="name">{{ $log->action ?? '-' }}</span>
                                    @if($log->description)
                                        <span class="sub">{{ $log->description }}</span>
                                    @endif
                                </td>
                                <td class="nw">
                                    @if($log->module)
                                        <span class="badge badge-soft">{{ $log->module }}</span>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                                <td class="nw"><span class="mono" style="color:var(--muted)">{{ $log->ip_address ?? '-' }}</span></td>
                                <td class="nw">
                                    @if($log->status === 'success')
                                        <span class="badge badge-ok">Berhasil</span>
                                    @elseif($log->status === 'failed')
                                        <span class="badge badge-danger">Gagal</span>
                                    @else
                                        <span class="badge badge-outline">{{ $log->status ? ucfirst($log->status) : 'Tidak diketahui' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="empty-cell">Belum ada audit log. Belum terdapat aktivitas yang tercatat pada tabel audit_logs.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

@endsection
