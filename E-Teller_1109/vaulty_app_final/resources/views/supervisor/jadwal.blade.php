@extends('layouts.supervisor')

@section('title', 'Jadwal Jaga')
@section('page-title', 'Jadwal Jaga')
@section('page-subtitle', 'Atur jadwal jaga per hari (Senin–Jumat). Cukup diatur sekali, berlaku setiap minggu.')

@section('content')
@php $aktif = $shifts->where('is_active', true); @endphp

<div class="stack">
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Jadwal Jaga Mingguan</h2>
                <p class="section-sub">
                    Pilih shift lalu centang nama petugas pada hari yang diinginkan.
                    Jadwal ini berlaku setiap minggu (bisa dipakai untuk sebulan/tahunan),
                    jadi tidak perlu diisi ulang tiap tanggal.
                </p>
            </div>
        </div>

        @if($aktif->isEmpty())
            <p class="sub" style="padding:0 16px 16px">
                Buat shift terlebih dahulu pada kartu "Shift Jaga" di bawah sebelum menyusun jadwal.
            </p>
        @endif

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:110px">Hari</th>
                        <th>Petugas Jaga</th>
                        <th style="width:330px">Tambah Petugas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hari as $dayNum => $dayName)
                        @php
                            $items = ($jadwal->get($dayNum) ?? collect())
                                ->sortBy(fn ($j) => ($j->shift?->start_time ?? '') . '|' . ($j->user?->name ?? ''));
                        @endphp
                        <tr>
                            <td class="name">{{ $dayName }}</td>
                            <td>
                                @forelse($items as $j)
                                    <form method="POST" action="{{ route('supervisor.jadwal.destroy', $j->id) }}"
                                          style="display:inline-block;margin:0 4px 4px 0"
                                          onsubmit="return confirm('Hapus jadwal {{ $j->user->name ?? '' }} pada hari {{ $dayName }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <span class="badge badge-soft">
                                            {{ $j->shift?->label() ?? '-' }}
                                            &middot; {{ $j->user->name ?? '-' }}@if($j->user?->kelas) ({{ $j->user->kelas }})@endif
                                            <button type="submit" aria-label="Hapus jadwal"
                                                    style="border:0;background:transparent;color:inherit;padding:0 0 0 4px;font-size:14px;line-height:1;cursor:pointer">&times;</button>
                                        </span>
                                    </form>
                                @empty
                                    <span class="sub">Belum ada petugas pada hari ini.</span>
                                @endforelse
                            </td>
                            <td>
                                @if($aktif->isNotEmpty())
                                    <form method="POST" action="{{ route('supervisor.jadwal.store') }}" class="stack" style="gap:6px">
                                        @csrf
                                        <input type="hidden" name="hari" value="{{ $dayNum }}">
                                        <select name="duty_shift_id" aria-label="Shift hari {{ $dayName }}" required>
                                            @foreach($aktif as $s)
                                                <option value="{{ $s->id }}">{{ $s->label() }}</option>
                                            @endforeach
                                        </select>
                                        <div style="max-height:104px;overflow:auto;border:1px solid var(--line-3);border-radius:8px;padding:6px 10px;display:grid;gap:4px">
                                            @forelse($tellers as $t)
                                                <label style="display:flex;gap:8px;align-items:center;font-weight:500;font-size:12px">
                                                    <input type="checkbox" name="petugas[]" value="{{ $t->id }}" style="width:auto;height:auto">
                                                    <span>{{ $t->name }} <span class="sub" style="margin:0">{{ $t->kelas }}</span></span>
                                                </label>
                                            @empty
                                                <span class="sub" style="margin:0">Belum ada petugas aktif.</span>
                                            @endforelse
                                        </div>
                                        <button class="btn btn-primary btn-sm" type="submit">Simpan {{ $dayName }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Shift Jaga</h2>
                <p class="section-sub">Jam shift sesuai kebijakan sekolah.</p>
            </div>
        </div>
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
@endsection