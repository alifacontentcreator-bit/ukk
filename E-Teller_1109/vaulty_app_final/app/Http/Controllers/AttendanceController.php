<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur attendance controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\DutyAttendance;
use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\ShiftSwapService;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * FILE INI UNTUK: Halaman "Absensi Jaga" milik TELLER.
 * =============================================================================
 *
 * Isi halaman hanya dua hal (sesuai aturan bisnis):
 * 1. Shift hari ini + tombol "Absen Masuk"/"Absen Pulang" (hanya bisa diklik
 *    pada hari dan jam shift tersebut).
 * 2. Jadwal jaga 7 hari ke depan beserta jam absen masuknya.
 *
 * Catatan penting:
 * - Riwayat kehadiran TIDAK ditampilkan di sini (hanya Supervisor yang boleh melihat).
 * - Status kehadiran (hadir/izin/sakit/alpa) TIDAK bisa diisi Teller; itu wewenang
 *   Supervisor pada halaman "Absensi Petugas".
 */
class AttendanceController extends Controller
{
    /**
     * Menampilkan halaman Absensi Jaga milik Teller yang sedang login.
     *
     * Catatan: jadwal yang dipakai adalah JADWAL EFEKTIF (sudah termasuk
     * hasil tukar shift yang disetujui), bukan hanya template mingguan.
     */
    public function index(Request $request, ShiftSwapService $swapService)
    {
        $teller = auth()->user();
        $idTeller = $teller->id;

        // Jadwal hari ini (efektif: bisa berisi shift hasil tukar shift).
        $jadwalHariIni = $swapService->jadwalEfektif($teller, today());

        // Catatan absensi hari ini (dipakai untuk menentukan tombol mana yang tampil).
        $absenHariIni = DutyAttendance::with('shift')
            ->where('user_id', $idTeller)
            ->whereDate('duty_date', today())
            ->get();

        // Jadwal 7 hari ke depan (efektif, Sabtu-Minggu dilewati).
        $jadwalTujuhHariKeDepan = collect(range(1, 7))
            ->map(fn (int $selisihHari) => today()->addDays($selisihHari))
            ->filter(fn ($tanggal) => $tanggal->dayOfWeekIso <= DutyWeeklySchedule::HARI_TERAKHIR)
            ->flatMap(fn ($tanggal) => $swapService->jadwalEfektif($teller, $tanggal))
            ->sortBy(fn ($item) => $item->duty_date->timestamp)
            ->values();

        // ================= Data untuk form "Ajukan Tukar Shift" =================
        $shiftSaya = DutyWeeklySchedule::with('shift')
            ->where('user_id', $idTeller)
            ->get()
            ->pluck('shift')
            ->filter()
            ->unique('id')
            ->values();

        /*
         * Tukar shift bersifat tukar-tukaran: Teller memilih siapa pun rekan
         * teller aktif sebagai pengganti, tanpa mensyaratkan rekan tersebut
         * punya shift pada tanggal yang dipilih.
         */
        $temanTeller = User::where('role', 'teller')
            ->where('status', 'active')
            ->where('id', '!=', $idTeller)
            ->orderBy('name')
            ->get();

        return view('teller.absensi', [
            'jadwalHariIni' => $jadwalHariIni,
            'absenHariIni' => $absenHariIni,
            'jadwalMendatang' => $jadwalTujuhHariKeDepan,
            'shiftSaya' => $shiftSaya,
            'temanTeller' => $temanTeller,
            'tanggalBolehDipilih' => ShiftSwapRequest::tanggalYangBolehDipilih(),
            'pengajuanTukarShift' => ShiftSwapRequest::with(['substitute', 'shift', 'approver'])
                ->where('requester_id', $idTeller)
                ->latest()
                ->limit(5)
                ->get(),
            'alasanTidakBolehAjukan' => $swapService->alasanTidakBolehAjukan($teller),
            'penggantiHariIni' => $swapService->penggantiHariIni($teller),
        ]);
    }

    /**
     * Absen MASUK. Hanya boleh untuk shift yang terjadwal hari ini
     * dan hanya di dalam jendela absen shift tersebut.
     */
    public function checkIn(Request $request, ShiftSwapService $swapService)
    {
        // Teller hanya boleh mengirim shift. Tanggal, jam, dan STATUS ditolak,
        // supaya Teller tidak bisa mengisi/memanipulasi status kehadiran.
        $dataAbsen = $request->validate([
            'duty_shift_id' => ['required', 'integer', 'exists:duty_shifts,id'],
            'duty_date' => ['prohibited'],
            'check_in_at' => ['prohibited'],
            'status' => ['prohibited'],
            'note' => ['prohibited'],
            'marked_by' => ['prohibited'],
        ], [
            'status.prohibited' => 'Status kehadiran hanya dapat ditetapkan Supervisor.',
            'note.prohibited' => 'Keterangan kehadiran hanya dapat diisi Supervisor.',
        ]);

        $idTeller = auth()->id();
        $idShift = $dataAbsen['duty_shift_id'];
        $waktuSekarang = now();

        // Pastikan Teller memang terjadwal pada shift ini hari ini.
        // Memakai jadwal EFEKTIF supaya teller pengganti (hasil tukar shift) juga bisa absen.
        $shiftTerjadwal = $swapService->jadwalEfektif(auth()->user(), today())
            ->first(fn ($item) => (int) $item->shift->id === (int) $idShift)
            ?->shift;

        if (!$shiftTerjadwal || !$shiftTerjadwal->is_active) {
            return back()->withErrors([
                'absensi' => 'Anda tidak terjadwal pada shift aktif tersebut hari ini.',
            ]);
        }

        // Pastikan sekarang masih di dalam jendela absen shift (mis. 06:45-09:30).
        $jendelaAbsen = DutyShift::checkInWindow($shiftTerjadwal);

        if (!$jendelaAbsen['allowed']) {
            return back()->withErrors([
                'absensi' => $jendelaAbsen['reason'] . ' untuk ' . $shiftTerjadwal->label() . '.',
            ]);
        }

        // Cegah absen ganda pada shift yang sama.
        $catatanKehadiran = DutyAttendance::where('user_id', $idTeller)
            ->whereDate('duty_date', today())
            ->where('duty_shift_id', $idShift)
            ->first();

        if ($catatanKehadiran) {
            // Pesan tidak menyebut status (izin/sakit/alpa) karena itu ranah Supervisor.
            return back()->withErrors([
                'absensi' => $catatanKehadiran->check_in_at
                    ? 'Anda sudah absen masuk pada shift ini.'
                    : 'Absensi Anda sudah dicatat Supervisor. Hubungi Supervisor bila ada kekeliruan.',
            ]);
        }

        DutyAttendance::create([
            'user_id' => $idTeller,
            'duty_shift_id' => $idShift,
            'duty_date' => today(),
            'check_in_at' => $waktuSekarang,
            // Kehadiran fisik otomatis "hadir" karena Teller benar-benar absen masuk.
            // Status lain (izin/sakit/alpa) hanya Supervisor yang mengisi.
            'status' => 'hadir',
        ]);

        return back()->with('success', 'Absen masuk tercatat pukul ' . $waktuSekarang->format('H:i') . '.');
    }

    /**
     * Absen PULANG. Hanya bisa dilakukan pada jam shift, setelah absen masuk.
     */
    public function checkOut(Request $request)
    {
        $dataAbsen = $request->validate([
            'duty_shift_id' => ['required', 'integer', 'exists:duty_shifts,id'],
            'duty_date' => ['prohibited'],
            'check_out_at' => ['prohibited'],
            // Status/keterangan bukan kewenangan Teller (hanya Supervisor).
            'status' => ['prohibited'],
            'note' => ['prohibited'],
        ]);

        $idShift = $dataAbsen['duty_shift_id'];

        // Cari catatan absen hari ini yang sudah masuk tetapi belum pulang.
        $catatanAbsen = DutyAttendance::with('shift')
            ->where('user_id', auth()->id())
            ->whereDate('duty_date', today())
            ->where('duty_shift_id', $idShift)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->first();

        if (!$catatanAbsen) {
            return back()->withErrors([
                'absensi' => 'Belum ada absen masuk yang bisa ditutup untuk shift ini.',
            ]);
        }

        $waktuSekarang = now();
        $jamShiftMulai = today()->setTimeFromTimeString($catatanAbsen->shift->start_time);
        $jamShiftSelesai = today()->setTimeFromTimeString($catatanAbsen->shift->end_time);

        if ($waktuSekarang->lt($jamShiftMulai) || $waktuSekarang->gt($jamShiftSelesai)) {
            return back()->withErrors([
                'absensi' => 'Absen pulang hanya dapat dilakukan pada jam shift ' . $catatanAbsen->shift->label() . '.',
            ]);
        }

        $catatanAbsen->update(['check_out_at' => $waktuSekarang]);

        return back()->with('success', 'Absen pulang tercatat pukul ' . $waktuSekarang->format('H:i') . '.');
    }
}