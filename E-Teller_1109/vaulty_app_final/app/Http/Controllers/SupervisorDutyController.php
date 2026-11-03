<?php

namespace App\Http\Controllers;

use App\Models\DutyAttendance;
use App\Models\DutySchedule;
use App\Models\DutyShift;
use App\Models\User;
use App\Services\PetugasSheet;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

/**
 * Sisi SUPERVISOR: absensi petugas, jadwal jaga & shift, dan data petugas (Excel).
 */
class SupervisorDutyController extends Controller
{
    // ===================== ABSENSI =====================

    private function attendanceQuery(Request $request): array
    {
        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:hadir,izin,sakit,alpa'],
        ]);

        $from = $f['from'] ?? today()->toDateString();
        $to = $f['to'] ?? $from;

        $query = DutyAttendance::with(['user', 'shift', 'marker'])
            ->whereDate('duty_date', '>=', $from)
            ->whereDate('duty_date', '<=', $to);

        if (!empty($f['teller_id'])) $query->where('user_id', $f['teller_id']);
        if (!empty($f['status'])) $query->where('status', $f['status']);

        return [$query, $from, $to];
    }

    public function attendanceIndex(Request $request)
    {
        [$query, $from, $to] = $this->attendanceQuery($request);

        $ringkasan = [];
        foreach (array_keys(DutyAttendance::STATUS) as $s) {
            $ringkasan[$s] = (clone $query)->where('status', $s)->count();
        }
        $ringkasan['terlambat'] = (clone $query)->where('status', 'hadir')->get()->filter(fn ($a) => $a->isLate())->count();

        $absensi = $query->orderByDesc('duty_date')->orderBy('check_in_at')->paginate(20)->withQueryString();

        // Terjadwal hari ini tetapi belum ada catatan kehadiran sama sekali.
        $belumAbsen = DutySchedule::with(['user', 'shift'])
            ->whereDate('duty_date', today())
            ->get()
            ->filter(fn ($j) => !DutyAttendance::where('user_id', $j->user_id)
                ->whereDate('duty_date', today())
                ->where('duty_shift_id', $j->duty_shift_id)
                ->exists())
            ->values();

        $tellers = User::where('role', 'teller')->orderBy('name')->get();

        return view('supervisor.absensi', compact('absensi', 'ringkasan', 'belumAbsen', 'tellers', 'from', 'to'));
    }

    public function attendanceMark(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'duty_date' => ['required', 'date'],
            'duty_shift_id' => ['nullable', 'integer', 'exists:duty_shifts,id'],
            'status' => ['required', 'in:izin,sakit,alpa'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (User::where('id', $data['user_id'])->where('role', 'teller')->doesntExist()) {
            return back()->withErrors(['absensi' => 'Pengguna yang dipilih bukan teller.']);
        }

        $attendance = DutyAttendance::firstOrNew([
            'user_id' => $data['user_id'],
            'duty_date' => $data['duty_date'],
            'duty_shift_id' => $data['duty_shift_id'] ?? null,
        ]);

        if ($attendance->check_in_at) {
            return back()->withErrors(['absensi' => 'Petugas ini sudah absen masuk, statusnya tidak dapat diubah menjadi ' . DutyAttendance::STATUS[$data['status']] . '.']);
        }

        $attendance->fill(['status' => $data['status'], 'note' => $data['note'] ?? null, 'marked_by' => auth()->id()])->save();

        return back()->with('success', 'Kehadiran dicatat sebagai ' . DutyAttendance::STATUS[$data['status']] . '.');
    }

    public function attendanceExport(Request $request, PetugasSheet $sheet)
    {
        [$query, $from, $to] = $this->attendanceQuery($request);

        $rows = $query->orderBy('duty_date')->orderBy('check_in_at')->get()->map(fn ($a) => [
            $a->user->name ?? '-',
            $a->user->kelas ?? '-',
            $a->user->no_telp ?? '',
            $a->duty_date->format('d/m/Y'),
            $a->shift ? $a->shift->name : 'Di luar jadwal',
            $a->check_in_at ? $a->check_in_at->format('H:i') : '',
            $a->check_out_at ? $a->check_out_at->format('H:i') : '',
            $a->statusLabel() . ($a->isLate() ? ' (terlambat)' : ''),
            $a->note ?? '',
        ]);

        return $sheet->download(
            "absensi-petugas_{$from}_sd_{$to}.xlsx",
            ['Nama', 'Kelas', 'No. WA', 'Tanggal', 'Shift', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan'],
            $rows,
            'Absensi'
        );
    }

    // ===================== JADWAL & SHIFT =====================

    public function scheduleIndex(Request $request)
    {
        $f = $request->validate(['minggu' => ['nullable', 'date']]);

        $start = Carbon::parse($f['minggu'] ?? today())->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(6);

        $jadwal = DutySchedule::with(['shift', 'user'])
            ->whereDate('duty_date', '>=', $start)
            ->whereDate('duty_date', '<=', $end)
            ->get()
            ->groupBy(fn ($j) => $j->duty_date->toDateString());

        $shifts = DutyShift::orderBy('start_time')->get();
        $tellers = User::where('role', 'teller')->where('status', 'active')->orderBy('name')->get();

        return view('supervisor.jadwal', compact('start', 'end', 'jadwal', 'shifts', 'tellers'));
    }

    public function scheduleStore(Request $request)
    {
        $data = $request->validate([
            'duty_shift_id' => ['required', 'integer', 'exists:duty_shifts,id'],
            'tanggal' => ['required', 'date', 'after_or_equal:today'],
            'petugas' => ['required', 'array', 'min:1', 'max:50'],
            'petugas.*' => ['integer', 'exists:users,id'],
        ], [
            'tanggal.after_or_equal' => 'Jadwal tidak dapat dibuat untuk tanggal yang sudah lewat.',
            'petugas.required' => 'Pilih minimal satu petugas.',
        ]);

        $tellerIds = User::where('role', 'teller')->whereIn('id', $data['petugas'])->pluck('id');

        $dibuat = 0;
        foreach ($tellerIds as $id) {
            $row = DutySchedule::firstOrCreate(
                ['duty_shift_id' => $data['duty_shift_id'], 'user_id' => $id, 'duty_date' => $data['tanggal']],
                ['created_by' => auth()->id()]
            );
            $dibuat += $row->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', $dibuat . ' petugas dijadwalkan.' . ($dibuat < $tellerIds->count() ? ' Sebagian sudah terjadwal sebelumnya.' : ''));
    }

    public function scheduleDestroy(int $id)
    {
        $schedule = DutySchedule::findOrFail($id);

        $sudahAbsen = DutyAttendance::where('user_id', $schedule->user_id)
            ->whereDate('duty_date', $schedule->duty_date)
            ->where('duty_shift_id', $schedule->duty_shift_id)
            ->whereNotNull('check_in_at')
            ->exists();

        if ($sudahAbsen) {
            return back()->withErrors(['jadwal' => 'Petugas sudah absen pada jadwal ini, jadi jadwalnya tidak dapat dihapus.']);
        }

        $schedule->delete();

        return back()->with('success', 'Jadwal dihapus.');
    }

    public function shiftStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ], [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        DutyShift::create($data + ['is_active' => true]);

        return back()->with('success', 'Shift "' . $data['name'] . '" ditambahkan.');
    }

    public function shiftToggle(int $id)
    {
        $shift = DutyShift::findOrFail($id);
        $shift->update(['is_active' => !$shift->is_active]);

        return back()->with('success', 'Shift ' . ($shift->is_active ? 'diaktifkan.' : 'dinonaktifkan. Jadwal lama tetap tersimpan.'));
    }

    public function shiftDestroy(int $id)
    {
        $shift = DutyShift::findOrFail($id);

        if ($shift->schedules()->exists()) {
            return back()->withErrors(['jadwal' => 'Shift ini sudah dipakai di jadwal. Nonaktifkan saja agar riwayatnya aman.']);
        }

        $shift->delete();

        return back()->with('success', 'Shift dihapus.');
    }

    // ===================== DATA PETUGAS (EXCEL) =====================

    private function officerQuery(Request $request)
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'wa' => ['nullable', 'in:belum'],
        ]);

        $query = User::where('role', 'teller');

        if (!empty($f['q'])) {
            $q = trim($f['q']);
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('kelas', 'like', "%{$q}%");
            });
        }
        if (($f['wa'] ?? null) === 'belum') {
            $query->where(fn ($w) => $w->whereNull('no_telp')->orWhere('no_telp', ''));
        }

        return $query->orderBy('name');
    }

    public function officerIndex(Request $request, PetugasSheet $sheet)
    {
        $petugas = $this->officerQuery($request)->paginate(20)->withQueryString();
        $tanpaWa = User::where('role', 'teller')->where(fn ($w) => $w->whereNull('no_telp')->orWhere('no_telp', ''))->count();

        return view('supervisor.petugas', ['petugas' => $petugas, 'tanpaWa' => $tanpaWa, 'sheet' => $sheet]);
    }

    public function officerImport(Request $request, PetugasSheet $sheet)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Pilih file Excel atau CSV terlebih dahulu.',
            'file.mimes' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ]);

        try {
            $hasil = $sheet->import($request->file('file'));
        } catch (DomainException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()
            ->with('success', "Impor selesai: {$hasil['updated']} nomor diperbarui, {$hasil['unchanged']} sudah sama, " . count($hasil['skipped']) . ' baris dilewati.')
            ->with('import_skipped', $hasil['skipped']);
    }

    public function officerExport(Request $request, PetugasSheet $sheet)
    {
        // ?templat=1 -> hanya baris judul, untuk diisi lalu diimpor kembali.
        $rows = $request->boolean('templat') ? [] : $this->officerQuery($request)->get()->map(fn ($u) => [
            $u->name, $u->kelas ?? '', $u->no_telp ?? '',
        ]);

        return $sheet->download(
            $request->boolean('templat') ? 'templat-data-petugas.xlsx' : 'data-petugas.xlsx',
            ['Nama', 'Kelas', 'No. WA yang bisa dihubungi'],
            $rows,
            'Petugas'
        );
    }
}