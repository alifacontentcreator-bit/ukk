@extends('layouts.teller')

@section('title', 'Absensi Jaga')
@section('page-title', 'Absensi Jaga')
@section('page-subtitle', 'Absen masuk dan pulang pada shift jaga Anda, lalu lihat jadwal dan riwayat kehadiran.')

@section('content')
@php
    $slots = $jadwalHariIni->isEmpty() ? collect([null]) : $jadwalHariIni->map(fn ($j) => $j->shift);
    $absenFor = fn ($shiftId) => $absenHariIni->first(fn ($a) => (int) $a->duty_shift_id === (int) $shiftId);
@endphp

<section class="card">
    <div class="section-head">
        <div><h2 class="section-title">Hari Ini</h2><p class="section-sub">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</p></div>
        @if($jadwalHariIni->isEmpty())<span class="badge badge-soft">Tidak ada jadwal</span>@endif
    </div>
    <div class="list">
        @foreach($slots as $shift)
            @php $a = $absenFor($shift?->id ?? 0); @endphp
            <div class="list-row">
                <div>
                    <div class="name">{{ $shift ? $shift->label() : 'Di luar jadwal' }}</div>
                    <div class="sub">
                        @if(!$a)
                            Belum absen
                        @elseif($a->status !== 'hadir')
                            Dicatat Supervisor: {{ $a->statusLabel() }}@if($a->note) &middot; {{ $a->note }}@endif
                        @else
                            Masuk {{ $a->check_in_at->format('H:i') }}@if($a->isLate()) <span class="badge badge-warn" style="margin-left:6px">Terlambat</span>@endif
                            @if($a->check_out_at) &middot; Pulang {{ $a->check_out_at->format('H:i') }} @endif
                        @endif
                    </div>
                </div>
                <div class="actions">
                    @if(!$a)
                        <form method="POST" action="{{ route('teller.absensi.masuk') }}">@csrf<input type="hidden" name="duty_shift_id" value="{{ $shift?->id }}"><button class="btn btn-primary btn-sm" type="submit"><x-icon name="check" />Absen Masuk</button></form>
                    @elseif($a->check_in_at && !$a->check_out_at)
                        <form method="POST" action="{{ route('teller.absensi.pulang') }}" onsubmit="return confirm('Catat absen pulang sekarang?')">@csrf<input type="hidden" name="duty_shift_id" value="{{ $shift?->id }}"><button class="btn btn-soft btn-sm" type="submit"><x-icon name="logout" />Absen Pulang</button></form>
                    @elseif($a->check_out_at)
                        <span class="badge badge-ok">Selesai</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>

<div class="section split-aside">
    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Jadwal 7 Hari ke Depan</h2><p class="section-sub">Diatur oleh Supervisor.</p></div></div>
        <div class="list">
            @forelse($jadwalMendatang as $j)
                <div class="list-row"><div><div class="name">{{ $j->duty_date->locale('id')->translatedFormat('l, d F') }}</div><div class="sub">{{ $j->shift?->label() ?? '-' }}</div></div></div>
            @empty
                <p class="sub" style="padding:24px 0;text-align:center">Belum ada jadwal jaga.</p>
            @endforelse
        </div>
    </section>

    <section class="card">
        <div class="section-head"><div><h2 class="section-title">Riwayat Kehadiran</h2><p class="section-sub">{{ $riwayat->total() }} catatan.</p></div></div>
        <form method="GET" action="{{ route('teller.absensi') }}" class="toolbar" data-auto-filter style="border-bottom:1px solid var(--line-2)">
            <div class="field"><label for="aFrom">Dari</label><input id="aFrom" type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field"><label for="aTo">Sampai</label><input id="aTo" type="date" name="to" value="{{ request('to') }}"></div>
            <div class="field"><label for="aStatus">Status</label>
                <select id="aStatus" name="status">
                    <option value="">Semua</option>
                    @foreach(\App\Models\DutyAttendance::STATUS as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($riwayat as $a)
                    <tr>
                        <td class="nw">{{ $a->duty_date->format('d/m/Y') }}</td>
                        <td>{{ $a->shift?->name ?? 'Di luar jadwal' }}</td>
                        <td class="nw">{{ $a->check_in_at?->format('H:i') ?? '-' }}</td>
                        <td class="nw">{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                        <td><span class="badge {{ $a->status === 'hadir' ? ($a->isLate() ? 'badge-warn' : 'badge-ok') : ($a->status === 'alpa' ? 'badge-danger' : 'badge-soft') }}">{{ $a->statusLabel() }}{{ $a->isLate() ? ' (terlambat)' : '' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-cell">Belum ada catatan kehadiran.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($riwayat->hasPages())<x-pager :paginator="$riwayat" />@endif
    </section>
</div>
@endsection