{{-- Tujuan file: Template Blade ini menyajikan antarmuka halaman absensi, termasuk data dan aksi yang disediakan aplikasi. --}}
@extends('layouts.teller')

@section('title', 'Absensi Jaga')
@section('page-title', 'Absensi Jaga')
@section('page-subtitle', 'Absen masuk hanya bisa ditekan pada jam shift Anda. Jadwal 7 hari ke depan beserta jam absen masuk ada di bawah.')

@section('content')
@php
    // Helper: ambil absensi hari ini milik teller untuk shift tertentu
    $absenFor = fn ($shiftId) => $absenHariIni->first(fn ($a) => (int) $a->duty_shift_id === (int) $shiftId);
@endphp

<div class="alert alert-info">
    <x-icon name="file" />
    <span>Halaman ini hanya menampilkan jadwal jaga 7 hari ke depan beserta jam absen masuk pada tiap tanggal. Data kehadiran dikelola Supervisor.</span>
</div>

{{-- Sapaan khusus bila hari ini Anda bertugas sebagai teller PENGGANTI (hasil tukar shift). --}}
@if($penggantiHariIni)
    <div class="alert alert-success">
        <x-icon name="check" />
        <span>
            <b>Selamat bekerja, Anda adalah teller pengganti hari ini.</b>
            Anda menggantikan {{ $penggantiHariIni->requester->name ?? 'rekan Anda' }}
            pada {{ $penggantiHariIni->shift?->label() ?? 'shift jaga' }}.
            Menu transaksi Anda sudah terbuka.
        </span>
    </div>
@endif

{{-- ===================== ABSENSI HARI INI ===================== --}}
<section class="card">
    <div class="section-head">
        <div>
            <h2 class="section-title">Absensi Hari Ini</h2>
            {{-- Tanggal ditampilkan sebagai teks, tidak bisa diubah --}}
            <p class="section-sub">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</p>
        </div>
        @if($jadwalHariIni->isEmpty())
            <span class="badge badge-soft">Tidak ada jadwal</span>
        @endif
    </div>
    <div class="list">
        @forelse($jadwalHariIni as $jadwal)
            @php
                $shift = $jadwal->shift;
                $a     = $absenFor($shift->id);
                // Gunakan checkInWindow agar logika sama dengan controller
                $window = \App\Models\DutyShift::checkInWindow($shift);
            @endphp
            <div class="list-row">
                <div>
                    <div class="name">{{ $shift->label() }}</div>
                    {{-- Teller hanya perlu tahu jam berapa absen masuk bisa dilakukan pada shift ini. --}}
                    <div class="sub">Absen masuk: {{ \App\Models\DutyShift::windowLabel($shift) }}</div>
                    <div class="sub">
                        @if(!$a)
                            Belum absen
                        @elseif($a->check_in_at)
                            Masuk {{ $a->check_in_at->format('H:i') }}
                            @if($a->check_out_at)
                                &middot; Pulang {{ $a->check_out_at->format('H:i') }}
                            @endif
                        @else
                            {{-- Sudah dicatat Supervisor (status kehadiran tidak ditampilkan ke Teller),
                                 dan Teller tidak memiliki check_in_at sendiri. --}}
                            Absensi Anda sudah dicatat Supervisor
                        @endif
                    </div>
                </div>
                <div class="actions">
                    @if(!$a && $window['allowed'])
                        {{-- Tombol Absen Masuk aktif --}}
                        <form method="POST" action="{{ route('teller.absensi.masuk') }}">@csrf
                            <input type="hidden" name="duty_shift_id" value="{{ $shift->id }}">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <x-icon name="check" /> Absen Masuk
                            </button>
                        </form>
                    @elseif(!$a && !$window['allowed'])
                        {{-- Di luar jam shift: tombol terkunci + alasannya ditampilkan --}}
                        <div>
                            <button class="btn btn-disabled btn-sm" type="button" disabled title="{{ $window['reason'] }}">
                                <x-icon name="check" /> Absen Masuk
                            </button>
                            <div class="sub">{{ $window['reason'] }}</div>
                        </div>
                    @elseif($a && !$a->check_in_at)
                        {{-- Sudah dicatat Supervisor (status ditetapkan Supervisor), tombol dikunci --}}
                        <span class="badge badge-soft">Sudah dicatat Supervisor</span>
                    @elseif($a && $a->check_in_at && !$a->check_out_at && $window['allowed'])
                        {{-- Tombol Absen Pulang aktif --}}
                        <form method="POST" action="{{ route('teller.absensi.pulang') }}" onsubmit="return confirm('Catat absen pulang sekarang?')">@csrf
                            <input type="hidden" name="duty_shift_id" value="{{ $shift->id }}">
                            <button class="btn btn-soft btn-sm" type="submit">
                                <x-icon name="logout" /> Absen Pulang
                            </button>
                        </form>
                    @elseif($a && $a->check_in_at && $a->check_out_at)
                        {{-- Sudah lengkap --}}
                        <span class="badge badge-ok">Selesai</span>
                    @else
                        <span class="badge badge-outline">Di luar jam shift</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="list-row">
                <div class="sub">Tidak ada jadwal jaga hari ini untuk Anda.</div>
                <button class="btn btn-disabled btn-sm" type="button" disabled title="Anda tidak memiliki shift hari ini.">
                    <x-icon name="check" /> Absen Masuk
                </button>
            </div>
        @endforelse
    </div>
</section>

{{-- Pesan sukses / error sudah ditampilkan otomatis oleh layout (layouts.shell). --}}

{{-- ===================== JADWAL 7 HARI KE DEPAN ===================== --}}
<section class="card">
    <div class="section-head">
        <div>
            <h2 class="section-title">Jadwal 7 Hari ke Depan</h2>
            <p class="section-sub">Jadwal shift Anda selama 7 hari ke depan beserta jam absen masuk pada tanggal tersebut.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Tanggal</th><th>Shift</th><th>Jam Shift</th><th>Absen Masuk</th></tr>
            </thead>
            <tbody>
                @forelse($jadwalMendatang as $j)
                    <tr>
                        <td class="nw">{{ $j->duty_date->locale('id')->translatedFormat('l, d F Y') }}</td>
                        <td>{{ $j->shift?->name ?? '-' }}</td>
                        {{-- Jam shift sebenarnya --}}
                        <td class="nw">{{ $j->shift ? substr($j->shift->start_time, 0, 5) . '-' . substr($j->shift->end_time, 0, 5) : '-' }}</td>
                        {{-- Jam Teller boleh menekan tombol Absen Masuk pada tanggal tersebut --}}
                        <td class="nw">{{ $j->shift ? \App\Models\DutyShift::windowLabel($j->shift, $j->duty_date) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">Tidak ada shift terjadwal dalam 7 hari ke depan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

{{-- ===================== AJUKAN TUKAR SHIFT ===================== --}}
<section class="card section">
    <div class="section-head">
        <div>
            <h2 class="section-title">Ajukan Tukar Shift</h2>
            <p class="section-sub">
                Bila Anda berhalangan (mis. ada rapat di sekolah), ajukan tukar shift ke teman.
                Pengajuan dikirim ke Supervisor; setelah disetujui, teman Anda yang bertugas pada tanggal itu.
            </p>
        </div>
    </div>

    @if($alasanTidakBolehAjukan)
        <div class="alert alert-warn" style="margin:0 16px 16px">
            <x-icon name="alert" />
            <span>{{ $alasanTidakBolehAjukan }}</span>
        </div>
    @elseif($shiftSaya->isEmpty())
        <p class="sub" style="padding:0 16px 16px">Anda belum punya shift pada jadwal jaga, jadi belum bisa mengajukan tukar shift.</p>
    @else
        <form method="POST" action="{{ route('teller.tukar-shift.store') }}" class="stack"
              style="padding:0 16px 16px;gap:10px"
              onsubmit="return confirm('Kirim pengajuan tukar shift ke Supervisor?')">
            @csrf

            <div class="cols-2">
                <div class="field">
                    <label for="tukarTanggal">Tanggal yang ditukar</label>
                    <select id="tukarTanggal" name="tanggal" required>
                        @foreach($tanggalBolehDipilih as $pilihan)
                            <option value="{{ $pilihan->toDateString() }}">{{ $pilihan->translatedFormat('l, d F Y') }}</option>
                        @endforeach
                    </select>
                    <span class="sub">Paling cepat 2 hari dari sekarang, maksimal 7 hari ke depan (hari kerja).</span>
                </div>

                <div class="field">
                    <label for="tukarShift">Shift Anda</label>
                    <select id="tukarShift" name="duty_shift_id" required>
                        @foreach($shiftSaya as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="tukarTeman">Teman pengganti (nama &amp; kelas)</label>
                <select id="tukarTeman" name="substitute_id" required>
                    @foreach($temanTeller as $teman)
                        <option value="{{ $teman->id }}">{{ $teman->name }} — {{ $teman->kelas ?: 'tanpa kelas' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="tukarAlasan">Alasan</label>
                <input id="tukarAlasan" type="text" name="reason" maxlength="255" required
                       placeholder="Contoh: ada rapat di sekolah pada tanggal tersebut">
            </div>

            <button class="btn btn-primary" type="submit">Kirim Pengajuan</button>
        </form>
    @endif

    {{-- Riwayat pengajuan tukar shift milik Teller ini. --}}
    @if($pengajuanTukarShift->isNotEmpty())
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Diajukan</th><th>Tanggal</th><th>Shift</th><th>Pengganti</th><th>Alasan</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @foreach($pengajuanTukarShift as $pengajuan)
                    <tr>
                        <td class="nw">{{ $pengajuan->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="nw">{{ $pengajuan->duty_date->translatedFormat('d M Y') }}</td>
                        <td>{{ $pengajuan->shift?->label() ?? '-' }}</td>
                        <td>{{ $pengajuan->substitute->name ?? '-' }}</td>
                        <td class="sub" style="white-space:normal">{{ $pengajuan->reason }}</td>
                        <td class="nw">
                            <span class="badge {{ match($pengajuan->status) {
                                'approved' => 'badge-ok',
                                'rejected' => 'badge-danger',
                                'cancelled' => 'badge-outline',
                                default => 'badge-warn',
                            } }}">{{ $pengajuan->statusLabel() }}</span>
                            @if($pengajuan->rejected_reason)<div class="sub">{{ $pengajuan->rejected_reason }}</div>@endif
                        </td>
                        <td class="nw">
                            @if($pengajuan->status === 'pending')
                                <form method="POST" action="{{ route('teller.tukar-shift.batalkan', $pengajuan->id) }}"
                                      onsubmit="return confirm('Batalkan pengajuan tukar shift ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-soft btn-sm" type="submit">Batalkan</button>
                                </form>
                            @else
                                <span class="sub">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@endsection
