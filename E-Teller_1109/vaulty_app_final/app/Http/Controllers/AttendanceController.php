<?php

namespace App\Http\Controllers;

use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\DutyWeeklySchedule;
use Illuminate\Http\Request;

/**
 * Sisi TELLER: absen masuk/pulang untuk shift jaga dan riwayat kehadiran sendiri.
 * Jadwal jaga diambil dari template mingguan (Senin–Jumat).
 * Teller di luar jadwal tetap bisa absen, tetapi dicatat "Di luar jadwal".
 */
class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'in:hadir,izin,sakit,alpa'],
        ]);

        // Jadwal hari ini mengikuti template mingguan (bukan tanggal).
        $jadwalHariIni = DutyWeeklySchedule::forActiveDate(today(), $userId);

        $absenHariIni = DutyAttendance::with('shift')
            ->where('user_id', $userId)
            ->whereDate('duty_date', today())
            ->get();

        // Jadwal 7 hari ke depan mengikuti template mingguan. Sabtu & Minggu
        // dilewati karena jadwal masuk sekolah hanya Senin–Jumat.
        $jadwalMendatang = collect(range(1, 7))
            ->map(fn (int $i) => today()->addDays($i))
            ->filter(fn ($tanggal) => $tanggal->dayOfWeekIso <= DutyWeeklySchedule::HARI_TERAKHIR)
            ->flatMap(fn ($tanggal) => DutyWeeklySchedule::forDate($tanggal, $userId))
            ->sortBy(fn ($j) => $j->duty_date->timestamp)
            ->values();

        $query = DutyAttendance::with(['shift', 'marker'])->where('user_id', $userId);
        if (!empty($f['from'])) $query->whereDate('duty_date', '>=', $f['from']);
        if (!empty($f['to'])) $query->whereDate('duty_date', '<=', $f['to']);
        if (!empty($f['status'])) $query->where('status', $f['status']);
        $riwayat = $query->orderByDesc('duty_date')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('teller.absensi', compact('jadwalHariIni', 'absenHariIni', 'jadwalMendatang', 'riwayat'));
    }

    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'duty_shift_id' => ['nullable', 'integer', 'exists:duty_shifts,id'],
        ]);

        $userId = auth()->id();
        $shiftId = $data['duty_shift_id'] ?? null;
        $note = null;

        // Jadwal hari ini dari template mingguan.
        $scheduled = DutyWeeklySchedule::where('user_id', $userId)
            ->where('day_of_week', today()->dayOfWeekIso);

        if ($shiftId) {
            if (!(clone $scheduled)->where('duty_shift_id', $shiftId)->exists()) {
                return back()->withErrors(['absensi' => 'Anda tidak terjadwal pada shift tersebut hari ini.']);
            }
        } elseif ((clone $scheduled)->exists()) {
            return back()->withErrors(['absensi' => 'Anda punya jadwal jaga hari ini. Absen melalui shift yang terjadwal.']);
        } else {
            $note = 'Di luar jadwal';
        }

        $existing = DutyAttendance::where('user_id', $userId)->whereDate('duty_date', today())
            ->when($shiftId, fn ($q) => $q->where('duty_shift_id', $shiftId), fn ($q) => $q->whereNull('duty_shift_id'))
            ->first();

        if ($existing) {
            return back()->withErrors(['absensi' => $existing->check_in_at
                ? 'Anda sudah absen masuk pada shift ini.'
                : 'Kehadiran Anda sudah dicatat Supervisor sebagai ' . $existing->statusLabel() . '. Hubungi Supervisor bila keliru.']);
        }

        DutyAttendance::create([
            'user_id' => $userId,
            'duty_shift_id' => $shiftId,
            'duty_date' => today(),
            'check_in_at' => now(),
            'status' => 'hadir',
            'note' => $note,
        ]);

        return back()->with('success', 'Absen masuk tercatat pukul ' . now()->format('H:i') . '.');
    }

    public function checkOut(Request $request)
    {
        $data = $request->validate([
            'duty_shift_id' => ['nullable', 'integer', 'exists:duty_shifts,id'],
        ]);

        $shiftId = $data['duty_shift_id'] ?? null;

        $attendance = DutyAttendance::where('user_id', auth()->id())->whereDate('duty_date', today())
            ->whereNotNull('check_in_at')->whereNull('check_out_at')
            ->when($shiftId, fn ($q) => $q->where('duty_shift_id', $shiftId), fn ($q) => $q->whereNull('duty_shift_id'))
            ->first();

        if (!$attendance) {
            return back()->withErrors(['absensi' => 'Belum ada absen masuk yang bisa ditutup untuk shift ini.']);
        }

        $attendance->update(['check_out_at' => now()]);

        return back()->with('success', 'Absen pulang tercatat pukul ' . now()->format('H:i') . '.');
    }
}