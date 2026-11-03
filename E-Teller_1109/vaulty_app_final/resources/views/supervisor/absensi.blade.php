@extends('layouts.supervisor')

@section('title', 'Absensi Petugas')
@section('page-title', 'Absensi Petugas')
@section('page-subtitle', 'Pantau kehadiran teller pada shift jaga. Petugas yang tidak hadir dapat ditandai izin, sakit, atau alpa.')
@section('page-actions')
    <a class="btn btn-soft btn-sm" href="{{ route('supervisor.absensi.export', request()->query()) }}"><x-icon name="down" /> Unduh Excel</a>
@endsection

@section('content')
<div class="grid-4">
@foreach([['Hadir', $ringkasan['hadir'], 'Sesuai filter', 'check'], ['Terlambat', $ringkasan['terlambat'], 'Dari yang hadir', 'clock'], ['Izin / Sakit', $ringkasan['izin'] + $ringkasan['sakit'], 'Ditandai Supervisor', 'file'], ['Alpa', $ringkasan['alpa'], 'Tanpa keterangan', 'x']] as [$label, $value, $note, $icon])
    <div class="card stat">
        <div class="stat-top"><span class="stat-label">{{ $label }}</span><span class="stat-icon"><x-icon :name="$icon" /></span></div>
        <div><div class="stat-value">{{ $value }}</div><div class="stat-note">{{ $note }}</div></div>
    </div>
@endforeach
</div>

@if($belumAbsen->count())
<section class="card section">
    <div class="section-head"><div><h2 class="section-title">Terjadwal Hari Ini, Belum Absen</h2><p class="section-sub">Tandai bila petugas tidak hadir.</p></div><span class="badge badge-warn">{{ $belumAbsen->count() }} petugas</span></div>
    <div class="list">
        @foreach($belumAbsen as $j)
            <div class="list-row">
                <div><div class="name">{{ $j->user->name ?? '-' }}</div><div class="sub">{{ $j->user->kelas ?? '-' }} &middot; {{ $j->shift?->label() ?? '-' }}</div></div>
                <button type="button" class="btn btn-soft btn-sm" data-mark data-user="{{ $j->user_id }}" data-date="{{ $j->duty_date->toDateString() }}" data-shift="{{ $j->duty_shift_id }}" data-name="{{ $j->user->name ?? '' }}">Tandai tidak hadir</button>
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="card section">
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
        <div class="field"><a href="{{ route('supervisor.absensi') }}" class="btn btn-soft btn-sm">Hari ini</a></div>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>Petugas</th><th>Shift</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($absensi as $a)
                <tr>
                    <td class="nw">{{ $a->duty_date->format('d/m/Y') }}</td>
                    <td><div class="name">{{ $a->user->name ?? '-' }}</div><div class="sub">{{ $a->user->kelas ?? '-' }}</div></td>
                    <td>{{ $a->shift?->label() ?? 'Di luar jadwal' }}</td>
                    <td class="nw">{{ $a->check_in_at?->format('H:i') ?? '-' }}</td>
                    <td class="nw">{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                    <td><span class="badge {{ $a->status === 'hadir' ? ($a->isLate() ? 'badge-warn' : 'badge-ok') : ($a->status === 'alpa' ? 'badge-danger' : 'badge-soft') }}">{{ $a->statusLabel() }}{{ $a->isLate() ? ' (terlambat)' : '' }}</span></td>
                    <td class="sub" style="min-width:160px;white-space:normal">{{ $a->note ?: '-' }}@if($a->marker)<div>oleh {{ $a->marker->name }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">Tidak ada catatan kehadiran pada rentang ini.</td></tr>
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
        <div><h3>Tandai tidak hadir</h3><p data-mark-name></p></div>
        <div class="field"><label for="mStatus">Status</label>
            <select id="mStatus" name="status"><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alpa">Alpa</option></select>
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
            form.status.value = 'izin';
            form.note.value = '';
            dialog.querySelector('[data-mark-name]').textContent = b.dataset.name;
            dialog.showModal();
            return;
        }
        if (e.target.closest('[data-mark-close]') || e.target === dialog) dialog.close();
    });
})();
</script>
@endpush