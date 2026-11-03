@extends('layouts.supervisor')

@section('title', 'Jadwal Jaga')
@section('page-title', 'Jadwal Jaga')
@section('page-subtitle', 'Atur shift jaga dan tentukan petugas yang bertugas pada tiap tanggal.')

@section('content')
@php $aktif = $shifts->where('is_active', true); @endphp

<div class="split">
    <section class="card">
        <div class="section-head">
            <div><h2 class="section-title">Jadwal Minggu Ini</h2><p class="section-sub">{{ $start->locale('id')->translatedFormat('d F') }} &ndash; {{ $end->locale('id')->translatedFormat('d F Y') }}</p></div>
            <div class="actions">
                <a class="btn btn-soft btn-sm" href="{{ route('supervisor.jadwal', ['minggu' => $start->copy()->subWeek()->toDateString()]) }}">&larr; Sebelumnya</a>
                <a class="btn btn-soft btn-sm" href="{{ route('supervisor.jadwal') }}">Minggu ini</a>
                <a class="btn btn-soft btn-sm" href="{{ route('supervisor.jadwal', ['minggu' => $start->copy()->addWeek()->toDateString()]) }}">Berikutnya &rarr;</a>
            </div>
        </div>
        <div class="list">
            @for($d = 0; $d < 7; $d++)
                @php $day = $start->copy()->addDays($d); $items = $jadwal->get($day->toDateString(), collect())->sortBy(fn ($j) => $j->shift?->start_time); @endphp
                <div class="list-row" style="align-items:flex-start">
                    <div style="min-width:120px"><div class="name">{{ $day->locale('id')->translatedFormat('l') }}</div><div class="sub">{{ $day->format('d/m/Y') }}@if($day->isToday()) &middot; hari ini @endif</div></div>
                    <div style="flex:1;display:flex;flex-direction:column;gap:8px">
                        @forelse($items->groupBy('duty_shift_id') as $group)
                            <div>
                                <div class="sub" style="margin:0 0 4px">{{ $group->first()->shift?->label() }}</div>
                                <div class="actions">
                                    @foreach($group as $j)
                                        <form method="POST" action="{{ route('supervisor.jadwal.destroy', $j->id) }}" onsubmit="return confirm('Hapus jadwal {{ $j->user->name ?? '' }}?')">@csrf @method('DELETE')
                                            <span class="badge badge-soft">{{ $j->user->name ?? '-' }} <button type="submit" aria-label="Hapus" style="border:0;background:transparent;color:inherit;padding:0 0 0 4px;font-size:14px;line-height:1">&times;</button></span>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <span class="sub">Belum ada jadwal.</span>
                        @endforelse
                    </div>
                </div>
            @endfor
        </div>
    </section>

    <div class="stack">
        <section class="card">
            <div class="section-head"><div><h2 class="section-title">Tambah Jadwal</h2><p class="section-sub">Pilih shift, tanggal, dan petugas.</p></div></div>
            @if($aktif->isEmpty())
                <p class="sub" style="padding:24px">Buat shift terlebih dahulu pada kartu di bawah.</p>
            @else
            <form method="POST" action="{{ route('supervisor.jadwal.store') }}" class="card-body stack">
                @csrf
                <div class="field"><label for="jShift">Shift</label>
                    <select id="jShift" name="duty_shift_id" required>@foreach($aktif as $s)<option value="{{ $s->id }}">{{ $s->label() }}</option>@endforeach</select>
                </div>
                <div class="field"><label for="jTanggal">Tanggal</label><input id="jTanggal" type="date" name="tanggal" value="{{ old('tanggal', today()->toDateString()) }}" min="{{ today()->toDateString() }}" required></div>
                <div class="field"><label>Petugas</label>
                    <div style="max-height:220px;overflow:auto;border:1px solid var(--line-3);border-radius:8px;padding:8px 12px;display:grid;gap:6px">
                        @foreach($tellers as $t)
                            <label style="display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" name="petugas[]" value="{{ $t->id }}" style="width:auto;height:auto"> {{ $t->name }} <span class="sub" style="margin:0">{{ $t->kelas }}</span></label>
                        @endforeach
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Simpan jadwal</button>
            </form>
            @endif
        </section>

        <section class="card">
            <div class="section-head"><div><h2 class="section-title">Shift Jaga</h2><p class="section-sub">Jam shift sesuai kebijakan sekolah.</p></div></div>
            <div class="list">
                @forelse($shifts as $s)
                    <div class="list-row">
                        <div><div class="name">{{ $s->name }}</div><div class="sub">{{ substr($s->start_time, 0, 5) }}&ndash;{{ substr($s->end_time, 0, 5) }}</div></div>
                        <div class="actions">
                            <form method="POST" action="{{ route('supervisor.shift.toggle', $s->id) }}">@csrf<button class="badge {{ $s->is_active ? 'badge-ok' : 'badge-soft' }}" type="submit" style="border:0;cursor:pointer">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</button></form>
                            <form method="POST" action="{{ route('supervisor.shift.destroy', $s->id) }}" onsubmit="return confirm('Hapus shift ini?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>
                        </div>
                    </div>
                @empty
                    <p class="sub" style="padding:16px 0">Belum ada shift.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('supervisor.shift.store') }}" class="card-body stack" style="border-top:1px solid var(--line-2)">
                @csrf
                <div class="field"><label for="sName">Nama shift</label><input id="sName" type="text" name="name" maxlength="60" placeholder="Contoh: Shift 1 (Istirahat 1)" required></div>
                <div class="cols-2">
                    <div class="field"><label for="sStart">Mulai</label><input id="sStart" type="time" name="start_time" required></div>
                    <div class="field"><label for="sEnd">Selesai</label><input id="sEnd" type="time" name="end_time" required></div>
                </div>
                <button class="btn btn-soft" type="submit">Tambah shift</button>
            </form>
        </section>
    </div>
</div>
@endsection