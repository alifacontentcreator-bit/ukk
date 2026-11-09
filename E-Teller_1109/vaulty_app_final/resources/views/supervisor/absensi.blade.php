@extends('layouts.supervisor')

@section('title', 'Absensi Petugas')
@section('page-title', 'Absensi Petugas')
@section('page-subtitle', 'Kehadiran hari ini mengikuti jadwal shift (dipisah dari riwayat kehadiran seluruh petugas).')
@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('supervisor.absensi.export', request()->query()) }}"><x-icon name="down" /> Unduh Excel</a>
@endsection

@section('content')
<div class="grid-4">
@foreach([
    ['Hadir', $ringkasanHariIni['hadir'], 'Absen masuk di halaman Teller', 'check'],
    ['Izin / Sakit', $ringkasanHariIni['izin'] + $ringkasanHariIni['sakit'], 'Konfirmasi langsung ke SPV', 'file'],
    ['Alpa', $ringkasanHariIni['alpa'], 'Tanpa absen & tanpa konfirmasi', 'x'],
    ['Belum Ditandai', $ringkasanHariIni['belum'], 'Menunggu absen / keputusan SPV', 'clock'],
] as [$label, $value, $note, $icon])
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">{{ $label }}</span><span class="stat-icon"><x-icon :name="$icon" /></span></div>
        <div><div class="stat-value">{{ $value }}</div><div class="stat-note">{{ $note }}</div></div>
    </div>
@endforeach
</div>

{{-- ===================== 1. KEHADIRAN HARI INI (SESUAI JADWAL SHIFT) ===================== --}}
<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Kehadiran Hari Ini</h2>
            <p class="section-sub">{{ now()->locale('id')->translatedFormat('l, d F Y') }} &middot; hanya petugas yang dijadwalkan shift pada hari ini.</p>
        </div>
        <span class="badge badge-soft">{{ $ringkasanHariIni['terjadwal'] }} petugas terjadwal</span>
    </div>

    <div class="alert alert-info" style="margin:0 0 12px">
        <x-icon name="file" />
        <span>Teller yang <b>absen masuk</b> di halaman Absensi Jaga otomatis berstatus <b>Hadir</b>.
        <b>Izin</b>/<b>Sakit</b> hanya untuk teller yang <b>konfirmasi langsung</b> ke Supervisor.
        Teller yang tidak absen dan tidak konfirmasi ditandai <b>Alpa</b>.</span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Petugas</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            @forelse($tabelHariIni as $row)
                @php
                    $catatan = $row->catatan;
                    $status  = $row->status;
                    $badge = match ($status) {
                        'hadir'         => $catatan?->isLate() ? 'badge-warn' : 'badge-ok',
                        'alpa'          => 'badge-danger',
                        'izin', 'sakit' => 'badge-soft',
                        default         => 'badge-warn',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="name">{{ $row->user?->name ?? '-' }}</div>
                        <div class="sub">{{ $row->user?->kelas ?? '-' }}</div>
                    </td>
                    <td>{{ $row->shift?->label() ?? '-' }}</td>
                    <td class="nw">{{ $catatan?->check_in_at?->format('H:i') ?? '-' }}</td>
                    <td class="nw">{{ $catatan?->check_out_at?->format('H:i') ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $badge }}">{{ $status ? (\App\Models\DutyAttendance::STATUS[$status] ?? ucfirst($status)) : 'Belum absen' }}{{ $catatan?->isLate() ? ' (terlambat)' : '' }}</span>
                        @if($row->otomatis)<div class="sub">otomatis dari absen Teller</div>@endif
                    </td>
                    <td class="sub" style="min-width:150px;white-space:normal">
                        {{ $catatan?->note ?: '-' }}
                        @if($catatan?->marker)<div>oleh {{ $catatan->marker->name }}</div>@endif
                    </td>
                    <td class="nw">
                        @if($catatan)
                            {{-- Sudah ada catatan (absen Teller / pernah ditandai SPV): ubah bila keliru. --}}
                            <button type="button" class="btn btn-soft btn-sm" data-mark
                                data-user="{{ $row->user?->id }}"
                                data-date="{{ today()->toDateString() }}"
                                data-shift="{{ $row->shift?->id }}"
                                data-name="{{ $row->user?->name }}"
                                data-status="{{ $status }}"
                                data-note="{{ $catatan->note }}">Ubah status</button>
                        @else
                            {{-- Belum absen: SPV menetapkan Izin/Sakit (bila teller konfirmasi langsung) atau Alpa. --}}
                            <div class="actions">
                                @foreach(['izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'] as $kode => $teks)
                                    <button type="button" class="btn btn-soft btn-sm" data-mark
                                        data-user="{{ $row->user?->id }}"
                                        data-date="{{ today()->toDateString() }}"
                                        data-shift="{{ $row->shift?->id }}"
                                        data-name="{{ $row->user?->name }}"
                                        data-status="{{ $kode }}">{{ $teks }}</button>
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Tidak ada petugas yang dijadwalkan shift hari ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- ===================== 2. RIWAYAT KEHADIRAN (SEMUA PETUGAS) ===================== --}}
<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Riwayat Kehadiran</h2>
            <p class="section-sub">Seluruh catatan absensi semua petugas &mdash; data lama tidak pernah dihapus. Kosongkan tanggal untuk melihat seluruh riwayat.</p>
        </div>
        <span class="badge badge-soft">{{ $absensi->total() }} catatan</span>
    </div>
    <form method="GET" action="{{ route('supervisor.absensi') }}" class="toolbar" data-auto-filter>
        <div class="field"><label for="sFrom">Dari tanggal</label><input id="sFrom" type="date" name="from" value="{{ $from }}"></div>
        <div class="field"><label for="sTo">Sampai tanggal</label><input id="sTo" type="date" name="to" value="{{ $to }}"></div>
        <div class="field"><label for="sTeller">Petugas</label>
            <select id="sTeller" name="teller_id">
                <option value="">Semua petugas</option>
                @foreach($tellers as $t)<option value="{{ $t->id }}" @selected((string) request('teller_id') === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label for="sStatus">Status</label>
            <select id="sStatus" name="status">
                <option value="">Semua</option>
                @foreach(\App\Models\DutyAttendance::STATUS as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <a href="{{ route('supervisor.absensi', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}" class="btn btn-soft btn-sm">Hari ini</a>
            <a href="{{ route('supervisor.absensi') }}" class="btn btn-soft btn-sm">Semua riwayat</a>
        </div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>Petugas</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($absensi as $catatan)
                <tr>
                    <td class="nw">{{ $catatan->duty_date->format('d/m/Y') }}</td>
                    <td>
                        <div class="name">{{ $catatan->user->name ?? '-' }}</div>
                        <div class="sub">{{ $catatan->user->kelas ?? '-' }}</div>
                    </td>
                    <td>{{ $catatan->shift?->label() ?? 'Di luar jadwal' }}</td>
                    <td class="nw">{{ $catatan->check_in_at?->format('H:i') ?? '-' }}</td>
                    <td class="nw">{{ $catatan->check_out_at?->format('H:i') ?? '-' }}</td>
                    <td>
                        {{-- Badge status: hadir (hijau), alpa (merah), izin/sakit (abu-abu). --}}
                        <span class="badge {{ $catatan->status === 'hadir' ? ($catatan->isLate() ? 'badge-warn' : 'badge-ok') : ($catatan->status === 'alpa' ? 'badge-danger' : 'badge-soft') }}">
                            {{ $catatan->statusLabel() }}{{ $catatan->isLate() ? ' (terlambat)' : '' }}
                        </span>
                    </td>
                    <td class="sub" style="min-width:160px;white-space:normal">
                        {{ $catatan->note ?: '-' }}
                        @if($catatan->marker)<div>oleh {{ $catatan->marker->name }}</div>@endif
                    </td>
                    <td class="nw">
                        {{-- Tombol ini membuka dialog untuk mengubah status kehadiran (khusus Supervisor). --}}
                        <button type="button" class="btn btn-soft btn-sm" data-mark
                            data-user="{{ $catatan->user_id }}"
                            data-date="{{ $catatan->duty_date->toDateString() }}"
                            data-shift="{{ $catatan->duty_shift_id }}"
                            data-name="{{ $catatan->user->name ?? '' }}"
                            data-status="{{ $catatan->status }}"
                            data-note="{{ $catatan->note }}">Ubah status</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">Tidak ada catatan kehadiran pada rentang ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($absensi->hasPages())<x-pager :paginator="$absensi" />@endif
</section>

<dialog class="et-dialog" id="markDialog">
    <form method="POST" action="{{ route('supervisor.absensi.tandai') }}">
        @csrf
        <input type="hidden" name="user_id"><input type="hidden" name="duty_date"><input type="hidden" name="duty_shift_id">
        <div><h3>Tandai / ubah status kehadiran</h3><p data-mark-name></p></div>
        <div class="field"><label for="mStatus">Status</label>
            <select id="mStatus" name="status"><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alpa">Alpa</option></select>
        </div>
        <div class="field"><label for="mNote">Keterangan (opsional)</label><input id="mNote" type="text" name="note" maxlength="255" placeholder="Contoh: surat izin dari wali kelas"></div>
        <div class="actions end"><button type="button" class="btn btn-soft" data-mark-close>Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    var dialog = document.getElementById('markDialog');
    if (!dialog) return;
    var form = dialog.querySelector('form');
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-mark]');
        if (b) {
            form.user_id.value = b.dataset.user;
            form.duty_date.value = b.dataset.date;
            form.duty_shift_id.value = b.dataset.shift || '';
            // Baris tabel mengirim status/keterangan saat ini (mode "Ubah status"),
            // sedangkan tombol "Tandai tidak hadir" tidak mengirim apa pun.
            form.status.value = b.dataset.status || 'izin';
            form.note.value = b.dataset.note || '';
            dialog.querySelector('[data-mark-name]').textContent = b.dataset.name;
            dialog.showModal();
            return;
        }
        if (e.target.closest('[data-mark-close]') || e.target === dialog) dialog.close();
    });
})();
</script>
@endpush