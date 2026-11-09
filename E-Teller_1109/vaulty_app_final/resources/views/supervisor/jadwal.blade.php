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
                            // $jadwal = kumpulan jadwal seluruh hari (dari controller),
                            // $items = jadwal khusus hari ini yang diurutkan per jam shift.
                            $items = ($jadwal->get($dayNum) ?? collect())
                                ->sortBy(fn ($baris) => ($baris->shift?->start_time ?? '') . '|' . ($baris->user?->name ?? ''));
                        @endphp
                        <tr>
                            <td class="name">{{ $dayName }}</td>
                            <td>
                                {{-- Daftar petugas jaga pada hari ini (tombol × = hapus satu baris). --}}
                                @forelse($items as $barisJadwal)
                                    <form method="POST" action="{{ route('supervisor.jadwal.destroy', $barisJadwal->id) }}"
                                          style="display:inline-block;margin:0 4px 4px 0"
                                          onsubmit="return confirm('Hapus jadwal {{ $barisJadwal->user->name ?? '' }} pada hari {{ $dayName }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <span class="badge badge-soft">
                                            {{ $barisJadwal->shift?->label() ?? '-' }}
                                            &middot; {{ $barisJadwal->user->name ?? '-' }}@if($barisJadwal->user?->kelas) ({{ $barisJadwal->user->kelas }})@endif
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
                                            @foreach($aktif as $shift)
                                                <option value="{{ $shift->id }}">{{ $shift->label() }}</option>
                                            @endforeach
                                        </select>
                                        <div style="max-height:104px;overflow:auto;border:1px solid var(--line-3);border-radius:8px;padding:6px 10px;display:grid;gap:4px">
                                            @forelse($tellers as $petugas)
                                                <label style="display:flex;gap:8px;align-items:center;font-weight:500;font-size:12px">
                                                    <input type="checkbox" name="petugas[]" value="{{ $petugas->id }}" style="width:auto;height:auto">
                                                    <span>{{ $petugas->name }} <span class="sub" style="margin:0">{{ $petugas->kelas }}</span></span>
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

    {{-- ===================== UBAH / HAPUS JADWAL ===================== --}}
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Ubah / Hapus Jadwal</h2>
                <p class="section-sub">
                    Pilih satu hari tertentu atau <b>Semua hari</b> (Senin–Jumat) untuk mengubah maupun menghapus jadwal sekaligus.
                    Riwayat kehadiran yang sudah tercatat tidak ikut terhapus.
                </p>
            </div>
        </div>

        <div class="cols-2" style="padding:0 16px 16px;align-items:start">
            <form method="POST" action="{{ route('supervisor.jadwal.bulk-update') }}" class="stack" id="formUbahJadwal"
                  onsubmit="return confirm('Terapkan perubahan jadwal pada hari yang dipilih?')">
                @csrf
                <h3 class="section-title" style="font-size:15px">Ubah Jadwal</h3>

                <div class="field"><label for="uHari">Hari</label>
                    <select id="uHari" name="hari" required>
                        <option value="all">Semua hari (Senin–Jumat)</option>
                        @foreach($hari as $num => $namaHari)<option value="{{ $num }}">{{ $namaHari }}</option>@endforeach
                    </select>
                </div>

                <div class="field"><label for="uAksi">Jenis perubahan</label>
                    <select id="uAksi" name="aksi" required>
                        <option value="shift">Ganti shift (petugas tetap)</option>
                        <option value="petugas">Atur ulang petugas pada shift</option>
                    </select>
                </div>

                <div class="field"><label for="uShift">Shift</label>
                    <select id="uShift" name="duty_shift_id" required>
                        @forelse($aktif as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->label() }}</option>
                        @empty
                            <option value="">Belum ada shift aktif</option>
                        @endforelse
                    </select>
                </div>

                <div class="field" data-mode="shift"><label for="uShiftLama">Dari shift (opsional)</label>
                    <select id="uShiftLama" name="shift_lama">
                        <option value="">Semua shift</option>
                        @foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->label() }}</option>@endforeach
                    </select>
                </div>

                <div class="field" data-mode="petugas">
                    <label>Petugas untuk shift tersebut</label>
                    <div style="max-height:120px;overflow:auto;border:1px solid var(--line-3);border-radius:8px;padding:6px 10px;display:grid;gap:4px">
                        @forelse($tellers as $petugas)
                            <label style="display:flex;gap:8px;align-items:center;font-weight:500;font-size:12px">
                                <input type="checkbox" name="petugas[]" value="{{ $petugas->id }}" style="width:auto;height:auto">
                                <span>{{ $petugas->name }} <span class="sub" style="margin:0">{{ $petugas->kelas }}</span></span>
                            </label>
                        @empty
                            <span class="sub" style="margin:0">Belum ada petugas aktif.</span>
                        @endforelse
                    </div>
                    <span class="sub">Jadwal lama pada shift &amp; hari terpilih diganti dengan pilihan ini.</span>
                </div>

                <button class="btn btn-primary btn-sm" type="submit">Terapkan Perubahan</button>
            </form>

            <form method="POST" action="{{ route('supervisor.jadwal.bulk-destroy') }}" class="stack"
                  onsubmit="return confirm('Hapus jadwal pada hari yang dipilih? Riwayat kehadiran tetap tersimpan.')">
                @csrf
                @method('DELETE')
                <h3 class="section-title" style="font-size:15px">Hapus Jadwal</h3>

                <div class="field"><label for="hHari">Hari</label>
                    <select id="hHari" name="hari" required>
                        <option value="all">Semua hari (Senin–Jumat)</option>
                        @foreach($hari as $num => $namaHari)<option value="{{ $num }}">{{ $namaHari }}</option>@endforeach
                    </select>
                </div>

                <div class="field"><label for="hShift">Shift (opsional)</label>
                    <select id="hShift" name="duty_shift_id">
                        <option value="">Semua shift</option>
                        @foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->label() }}</option>@endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Petugas (opsional)</label>
                    <div style="max-height:120px;overflow:auto;border:1px solid var(--line-3);border-radius:8px;padding:6px 10px;display:grid;gap:4px">
                        @forelse($tellers as $petugas)
                            <label style="display:flex;gap:8px;align-items:center;font-weight:500;font-size:12px">
                                <input type="checkbox" name="petugas[]" value="{{ $petugas->id }}" style="width:auto;height:auto">
                                <span>{{ $petugas->name }} <span class="sub" style="margin:0">{{ $petugas->kelas }}</span></span>
                            </label>
                        @empty
                            <span class="sub" style="margin:0">Belum ada petugas aktif.</span>
                        @endforelse
                    </div>
                    <span class="sub">Kosongkan bila ingin menghapus seluruh jadwal pada hari/shift terpilih.</span>
                </div>

                <button class="btn btn-danger btn-sm" type="submit">Hapus Jadwal</button>
            </form>
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
                @forelse($shifts as $shift)
                    <div class="list-row">
                        <div><div class="name">{{ $shift->name }}</div><div class="sub">{{ substr($shift->start_time, 0, 5) }}&ndash;{{ substr($shift->end_time, 0, 5) }}</div></div>
                        <div class="actions">
                            <form method="POST" action="{{ route('supervisor.shift.toggle', $shift->id) }}">@csrf<button class="badge {{ $shift->is_active ? 'badge-ok' : 'badge-soft' }}" type="submit" style="border:0;cursor:pointer">{{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}</button></form>
                            <form method="POST" action="{{ route('supervisor.shift.destroy', $shift->id) }}" onsubmit="return confirm('Hapus shift ini?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit">Hapus</button></form>
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

@push('scripts')
<script>
(function () {
    var aksi = document.getElementById('uAksi');
    var form = document.getElementById('formUbahJadwal');

    if (!aksi || !form) {
        return;
    }

    function syncMode() {
        form.querySelectorAll('[data-mode]').forEach(function (el) {
            el.style.display = (el.dataset.mode === aksi.value) ? '' : 'none';
        });
    }

    aksi.addEventListener('change', syncMode);
    syncMode();
})();
</script>
@endpush