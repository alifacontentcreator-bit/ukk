<?php

namespace App\Http\Controllers;

use App\Models\DutyAttendance;
use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\User;
use App\Services\ExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sisi SUPERVISOR: absensi petugas, jadwal jaga & shift, dan data petugas.
 */
/**
 * =============================================================================
 * FILE INI UNTUK: Semua halaman SUPERVISOR yang berhubungan dengan petugas.
 * =============================================================================
 *
 * PETA FUNGSI (memudahkan pencarian saat presentasi):
 * - ABSENSI PETUGAS
 *   • attendanceIndex()   → tampilkan tabel "Kehadiran Hari Ini" + "Riwayat Kehadiran".
 *   • attendanceMark()    → Supervisor menetapkan status hadir/izin/sakit/alpa.
 *   • attendanceExport()  → unduh absensi ke Excel.
 *   • attendanceQuery()   → helper filter tanggal/petugas/status untuk absensi.
 *
 * - JADWAL JAGA (template mingguan Senin-Jumat)
 *   • scheduleIndex()        → tampilkan jadwal mingguan + daftar shift.
 *   • scheduleStore()        → tambah petugas ke jadwal pada hari tertentu.
 *   • scheduleDestroy()      → hapus satu baris jadwal.
 *   • scheduleBulkUpdate()   → ubah jadwal untuk hari tertentu / semua hari.
 *   • scheduleBulkDestroy()  → hapus jadwal untuk hari tertentu / semua hari.
 *   • validasiHari()/labelHari() → helper validasi & label hari.
 *
 * - SHIFT JAGA
 *   • shiftStore() / shiftToggle() / shiftDestroy() → kelola master shift.
 *
 * - DATA PETUGAS
 *   • officerIndex() → daftar teller (cari berdasarkan nama & kelas).
 *   • officerQuery() → helper filter pencarian petugas.
 */
class SupervisorDutyController extends Controller
{
    // =========================================================================
    // BAGIAN 1: ABSENSI PETUGAS
    // =========================================================================

    /**
     * Helper filter untuk tabel absensi:
     * tanggal awal/akhir, petugas tertentu, dan status kehadiran.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: ?string, 2: ?string}
     */
    private function attendanceQuery(Request $request): array
    {
        $filter = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:hadir,izin,sakit,alpa'],
        ]);

        $tanggalAwal = $filter['from'] ?? null;
        $tanggalAkhir = $filter['to'] ?? null;

        // Tanpa filter tanggal, tampilkan SELURUH riwayat kehadiran supaya tidak
        // ada data lama yang seolah-olah hilang dari halaman Supervisor.
        $queryAbsensi = DutyAttendance::with(['user', 'shift', 'marker']);

        if ($tanggalAwal) {
            $queryAbsensi->whereDate('duty_date', '>=', $tanggalAwal);
        }

        if ($tanggalAkhir) {
            $queryAbsensi->whereDate('duty_date', '<=', $tanggalAkhir);
        }

        if (!empty($filter['teller_id'])) {
            $queryAbsensi->where('user_id', $filter['teller_id']);
        }

        if (!empty($filter['status'])) {
            $queryAbsensi->where('status', $filter['status']);
        }

        return [$queryAbsensi, $tanggalAwal, $tanggalAkhir];
    }

    /**
     * Halaman Absensi Petugas.
     *
     * Dua bagian terpisah:
     * 1) Tabel kehadiran HARI INI — hanya petugas yang terjadwal shift pada
     *    hari itu (mengikuti template jadwal mingguan buatan Supervisor).
     * 2) Riwayat kehadiran — seluruh catatan absensi semua petugas (bisa difilter).
     */
    public function attendanceIndex(Request $request)
    {
        [$queryAbsensi, $tanggalAwal, $tanggalAkhir] = $this->attendanceQuery($request);

        // Tabel riwayat kehadiran (semua petugas, bisa difilter).
        $riwayatAbsensi = $queryAbsensi->orderByDesc('duty_date')
            ->orderBy('check_in_at')
            ->paginate(20)
            ->withQueryString();

        // ===== Tabel kehadiran hari ini: hanya petugas yang terjadwal hari ini =====
        // Contoh: jadwal Senin (Adi, Mira, Alex) → tabel Senin hanya memuat mereka,
        // dan otomatis berganti ke jadwal Selasa (Edo, Maya, Nina) pada hari Selasa.
        $catatanHariIni = DutyAttendance::with('marker')
            ->whereDate('duty_date', today())
            ->get()
            ->keyBy(fn ($catatan) => $catatan->user_id . ':' . (int) $catatan->duty_shift_id);

        $tabelHariIni = DutyWeeklySchedule::forActiveDate(today())
            ->sortBy(fn ($jadwal) => sprintf('%s|%s', $jadwal->shift?->start_time, (string) $jadwal->user?->name))
            ->values()
            ->map(function ($jadwal) use ($catatanHariIni) {
                $catatan = $catatanHariIni->get($jadwal->user_id . ':' . (int) $jadwal->duty_shift_id);

                return (object) [
                    'jadwal' => $jadwal,
                    'user' => $jadwal->user,
                    'shift' => $jadwal->shift,
                    'catatan' => $catatan,
                    // Absen masuk di halaman Teller otomatis berstatus "hadir".
                    'status' => $catatan?->status,
                    // Penanda: kehadiran berasal dari absen mandiri Teller (bukan ditandai SPV).
                    'otomatis' => (bool) ($catatan?->check_in_at && !$catatan->marked_by),
                ];
            });

        // Ringkasan angka di kartu atas halaman.
        $ringkasanHariIni = [
            'terjadwal' => $tabelHariIni->count(),
            'hadir' => $tabelHariIni->where('status', 'hadir')->count(),
            'izin' => $tabelHariIni->where('status', 'izin')->count(),
            'sakit' => $tabelHariIni->where('status', 'sakit')->count(),
            'alpa' => $tabelHariIni->where('status', 'alpa')->count(),
            'belum' => $tabelHariIni->whereNull('status')->count(),
        ];

        $daftarTeller = User::where('role', 'teller')->orderBy('name')->get();

        return view('supervisor.absensi', [
            'absensi' => $riwayatAbsensi,
            'tabelHariIni' => $tabelHariIni,
            'ringkasanHariIni' => $ringkasanHariIni,
            'tellers' => $daftarTeller,
            'from' => $tanggalAwal,
            'to' => $tanggalAkhir,
        ]);
    }

    /**
     * Supervisor menetapkan / mengubah status kehadiran petugas
     * (hadir / izin / sakit / alpa) beserta keterangannya.
     */
    public function attendanceMark(Request $request)
    {
        $dataAbsensi = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'duty_date' => ['required', 'date'],
            'duty_shift_id' => ['nullable', 'integer', 'exists:duty_shifts,id'],
            'status' => ['required', 'in:hadir,izin,sakit,alpa'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (User::where('id', $dataAbsensi['user_id'])->where('role', 'teller')->doesntExist()) {
            return back()->withErrors(['absensi' => 'Pengguna yang dipilih bukan teller.']);
        }

        $idShift = $dataAbsensi['duty_shift_id'] ?? null;

        // Cari catatan yang sudah ada untuk petugas + tanggal + shift yang sama.
        // whereDate dipakai agar pencocokan tetap benar walau kolom date
        // tersimpan sebagai "Y-m-d H:i:s" (mis. catatan absen masuk Teller).
        $catatan = DutyAttendance::query()
            ->where('user_id', $dataAbsensi['user_id'])
            ->whereDate('duty_date', $dataAbsensi['duty_date'])
            ->when(
                $idShift,
                fn ($query) => $query->where('duty_shift_id', $idShift),
                fn ($query) => $query->whereNull('duty_shift_id')
            )
            ->first();

        $catatanBaru = !$catatan;

        if ($catatanBaru) {
            $catatan = new DutyAttendance([
                'user_id' => $dataAbsensi['user_id'],
                'duty_date' => $dataAbsensi['duty_date'],
                'duty_shift_id' => $idShift,
            ]);
        }

        $statusSebelumnya = $catatan->exists ? $catatan->status : null;

        // Simpan sekaligus jejak audit (siapa yang mengubah & status sebelumnya).
        $catatan->fill([
            'status' => $dataAbsensi['status'],
            'note' => $dataAbsensi['note'] ?? null,
            'marked_by' => $catatanBaru ? auth()->id() : $catatan->marked_by,
            'updated_by' => $catatanBaru ? null : auth()->id(),
            'previous_status' => $catatanBaru ? null : $statusSebelumnya,
        ])->save();

        return back()->with(
            'success',
            'Kehadiran ' . ($catatanBaru ? 'dicatat' : 'diperbarui') .
            ' sebagai ' . DutyAttendance::STATUS[$dataAbsensi['status']] . '.'
        );
    }

    /**
     * Unduh data absensi petugas (sesuai filter yang sedang aktif) ke Excel.
     */
    public function attendanceExport(Request $request, ExcelExport $excel)
    {
        [$queryAbsensi, $tanggalAwal, $tanggalAkhir] = $this->attendanceQuery($request);

        $barisExcel = $queryAbsensi->orderBy('duty_date')
            ->orderBy('check_in_at')
            ->get()
            ->map(fn ($catatan) => [
                $catatan->user->name ?? '-',
                $catatan->user->kelas ?? '-',
                $catatan->duty_date->format('d/m/Y'),
                $catatan->shift ? $catatan->shift->name : 'Di luar jadwal',
                $catatan->check_in_at ? $catatan->check_in_at->format('H:i') : '',
                $catatan->check_out_at ? $catatan->check_out_at->format('H:i') : '',
                $catatan->statusLabel() . ($catatan->isLate() ? ' (terlambat)' : ''),
                $catatan->note ?? '',
            ]);

        return $excel->download(
            "absensi-petugas_{$tanggalAwal}_sd_{$tanggalAkhir}.xlsx",
            ['Nama', 'Kelas', 'Tanggal', 'Shift', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan'],
            $barisExcel,
            'Absensi'
        );
    }

    // ===================== JADWAL & SHIFT =====================

    /**
     * Halaman jadwal jaga: template mingguan (Senin–Jumat).
     *
     * Supervisor mengatur sekali per hari dalam minggu, lalu jadwal
     * otomatis berlaku setiap minggu (tidak perlu diisi tiap tanggal).
     */
    public function scheduleIndex()
    {
        $jadwal = DutyWeeklySchedule::with(['shift', 'user'])
            ->orderBy('day_of_week')
            ->get()
            ->groupBy('day_of_week');

        $shifts = DutyShift::orderBy('start_time')->get();
        $tellers = User::where('role', 'teller')->where('status', 'active')->orderBy('name')->get();
        $hari = DutyWeeklySchedule::HARI;

        return view('supervisor.jadwal', compact('jadwal', 'shifts', 'tellers', 'hari'));
    }

    /**
     * Menambah petugas ke jadwal pada hari tertentu (template mingguan).
     */
    public function scheduleStore(Request $request)
    {
        $dataJadwal = $request->validate([
            // Jadwal hanya Senin-Jumat (Sabtu & Minggu tidak dijadwalkan).
            'hari' => ['required', 'integer', Rule::in(array_keys(DutyWeeklySchedule::HARI))],
            'duty_shift_id' => ['required', 'integer', 'exists:duty_shifts,id'],
            'petugas' => ['required', 'array', 'min:1', 'max:50'],
            'petugas.*' => ['integer', 'exists:users,id'],
        ], [
            'hari.required' => 'Pilih hari jaga.',
            'hari.in' => 'Jadwal jaga hanya Senin sampai Jumat.',
            'petugas.required' => 'Pilih minimal satu petugas.',
        ]);

        $shiftDipilih = DutyShift::findOrFail($dataJadwal['duty_shift_id']);

        if (!$shiftDipilih->is_active) {
            return back()->withErrors([
                'jadwal' => 'Shift "' . $shiftDipilih->name . '" sedang nonaktif. Aktifkan dahulu bila ingin dipakai.',
            ]);
        }

        $idPetugasTerpilih = User::where('role', 'teller')
            ->whereIn('id', $dataJadwal['petugas'])
            ->pluck('id');

        $jumlahDitambahkan = 0;

        foreach ($idPetugasTerpilih as $idPetugas) {
            $jadwal = DutyWeeklySchedule::firstOrCreate(
                [
                    'day_of_week' => $dataJadwal['hari'],
                    'duty_shift_id' => $dataJadwal['duty_shift_id'],
                    'user_id' => $idPetugas,
                ],
                ['created_by' => auth()->id()]
            );

            // Hanya dihitung bila benar-benar baris baru (bukan sudah ada).
            $jumlahDitambahkan += $jadwal->wasRecentlyCreated ? 1 : 0;
        }

        $namaHari = DutyWeeklySchedule::HARI[$dataJadwal['hari']] ?? '';

        return back()->with(
            'success',
            $jumlahDitambahkan . ' petugas ditambahkan ke jadwal hari ' . $namaHari .
            '. Jadwal ini berlaku setiap minggu sampai diubah.'
        );
    }

    /**
     * Menghapus satu petugas dari jadwal hari tertentu.
     */
    public function scheduleDestroy(int $id)
    {
        $jadwal = DutyWeeklySchedule::with('user')->findOrFail($id);

        $nama = $jadwal->user->name ?? 'Petugas';
        $hari = $jadwal->dayLabel();

        $jadwal->delete();

        return back()->with('success', 'Jadwal ' . $nama . ' pada hari ' . $hari . ' dihapus. Riwayat kehadiran yang sudah tercatat tetap tersimpan.');
    }

    /** Validasi pilihan hari: angka 1–5 (Senin–Jumat) atau "all" (semua hari). */
    private function validasiHari(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value === 'all') {
                return;
            }

            if (!is_numeric($value) || !array_key_exists((int) $value, DutyWeeklySchedule::HARI)) {
                $fail('Pilih hari jaga yang valid (Senin–Jumat) atau "Semua hari".');
            }
        };
    }

    /** Label hari untuk pesan: "Senin" atau "semua hari (Senin–Jumat)". */
    private function labelHari(?int $hariAngka): string
    {
        return $hariAngka
            ? (DutyWeeklySchedule::HARI[$hariAngka] ?? '-')
            : 'semua hari (Senin–Jumat)';
    }

    /**
     * Mengubah jadwal sekaligus untuk hari tertentu atau semua hari.
     *
     * aksi = "shift"   → mengganti shift pada jadwal terpilih (petugas tetap).
     * aksi = "petugas" → mengatur ulang daftar petugas pada shift terpilih.
     */
    public function scheduleBulkUpdate(Request $request)
    {
        $data = $request->validate([
            'hari' => ['required', $this->validasiHari()],
            'aksi' => ['required', Rule::in(['shift', 'petugas'])],
            'duty_shift_id' => ['required', 'integer', 'exists:duty_shifts,id'],
            'shift_lama' => ['nullable', 'integer', 'exists:duty_shifts,id'],
            'petugas' => [Rule::requiredIf(fn () => $request->input('aksi') === 'petugas'), 'array'],
            'petugas.*' => ['integer', 'exists:users,id'],
        ], [
            'hari.required' => 'Pilih hari yang akan diubah.',
            'aksi.required' => 'Pilih jenis perubahan jadwal.',
            'duty_shift_id.required' => 'Pilih shift yang akan dipakai.',
            'petugas.required' => 'Pilih minimal satu petugas untuk shift tersebut.',
        ]);

        $shiftBaru = DutyShift::findOrFail($data['duty_shift_id']);

        if (!$shiftBaru->is_active) {
            return back()->withErrors(['jadwal' => 'Shift "' . $shiftBaru->name . '" sedang nonaktif. Aktifkan dahulu bila ingin dipakai.']);
        }

        $hariAngka = $data['hari'] === 'all' ? null : (int) $data['hari'];
        $label = $this->labelHari($hariAngka);

        $diubah = 0;
        $digabung = 0;
        $dibuat = 0;

        DB::transaction(function () use ($data, $hariAngka, $shiftBaru, &$diubah, &$digabung, &$dibuat) {
            if ($data['aksi'] === 'shift') {
                $query = DutyWeeklySchedule::query();

                if ($hariAngka) {
                    $query->where('day_of_week', $hariAngka);
                }

                if (!empty($data['shift_lama'])) {
                    $query->where('duty_shift_id', $data['shift_lama']);
                }

                foreach ($query->get() as $row) {
                    // Bila petugas tersebut sudah ada pada shift baru di hari yang
                    // sama, baris lama digabung (dihapus) agar tidak duplikat.
                    $duplikat = DutyWeeklySchedule::where('day_of_week', $row->day_of_week)
                        ->where('duty_shift_id', $shiftBaru->id)
                        ->where('user_id', $row->user_id)
                        ->where('id', '!=', $row->id)
                        ->exists();

                    if ($duplikat) {
                        $row->delete();
                        $digabung++;

                        continue;
                    }

                    if ((int) $row->duty_shift_id !== (int) $shiftBaru->id) {
                        $row->update(['duty_shift_id' => $shiftBaru->id]);
                        $diubah++;
                    }
                }

                return;
            }

            // aksi = "petugas": atur ulang petugas pada shift terpilih.
            $petugasIds = User::where('role', 'teller')
                ->whereIn('id', $data['petugas'] ?? [])
                ->pluck('id');

            $hariTarget = $hariAngka ? [$hariAngka] : array_keys(DutyWeeklySchedule::HARI);

            foreach ($hariTarget as $hari) {
                DutyWeeklySchedule::where('day_of_week', $hari)
                    ->where('duty_shift_id', $shiftBaru->id)
                    ->delete();

                foreach ($petugasIds as $id) {
                    DutyWeeklySchedule::firstOrCreate(
                        [
                            'day_of_week' => $hari,
                            'duty_shift_id' => $shiftBaru->id,
                            'user_id' => $id,
                        ],
                        ['created_by' => auth()->id()]
                    );

                    $dibuat++;
                }
            }
        });

        if ($data['aksi'] === 'shift') {
            $pesan = 'Jadwal ' . $label . ' diubah ke ' . $shiftBaru->label() . '. ' . $diubah . ' baris diperbarui.';

            if ($digabung > 0) {
                $pesan .= ' ' . $digabung . ' baris duplikat digabung.';
            }
        } else {
            $pesan = 'Petugas shift ' . $shiftBaru->label() . ' pada ' . $label . ' diatur ulang menjadi ' . $dibuat . ' petugas.';
        }

        return back()->with('success', $pesan);
    }

    /**
     * Menghapus jadwal sekaligus untuk hari tertentu atau semua hari,
     * dengan filter shift dan/atau petugas (opsional).
     */
    public function scheduleBulkDestroy(Request $request)
    {
        $data = $request->validate([
            'hari' => ['required', $this->validasiHari()],
            'duty_shift_id' => ['nullable', 'integer', 'exists:duty_shifts,id'],
            'petugas' => ['nullable', 'array'],
            'petugas.*' => ['integer', 'exists:users,id'],
        ], [
            'hari.required' => 'Pilih hari yang akan dihapus.',
        ]);

        $hariAngka = $data['hari'] === 'all' ? null : (int) $data['hari'];

        $query = DutyWeeklySchedule::query();

        if ($hariAngka) {
            $query->where('day_of_week', $hariAngka);
        }

        if (!empty($data['duty_shift_id'])) {
            $query->where('duty_shift_id', $data['duty_shift_id']);
        }

        if (!empty($data['petugas'])) {
            $query->whereIn('user_id', $data['petugas']);
        }

        $jumlah = $query->count();

        if ($jumlah === 0) {
            return back()->withErrors(['jadwal' => 'Tidak ada jadwal yang cocok dengan pilihan tersebut.']);
        }

        $query->delete();

        return back()->with(
            'success',
            $jumlah . ' jadwal pada ' . $this->labelHari($hariAngka) .
            ' dihapus. Riwayat kehadiran yang sudah tercatat tetap tersimpan.'
        );
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

        // Riwayat kehadiran tidak boleh hilang: shift yang sudah dipakai jadwal
        // maupun absensi tidak boleh dihapus (cukup dinonaktifkan).
        if ($shift->schedules()->exists() || $shift->weeklySchedules()->exists() || $shift->attendances()->exists()) {
            return back()->withErrors(['jadwal' => 'Shift ini sudah dipakai di jadwal/riwayat absensi. Nonaktifkan saja agar riwayatnya aman.']);
        }

        $shift->delete();

        return back()->with('success', 'Shift dihapus.');
    }

    // ===================== DATA PETUGAS =====================

    /**
     * Helper filter pencarian data petugas:
     * cari berdasarkan NAMA dan KELAS (bukan status akun).
     */
    private function officerQuery(Request $request)
    {
        $filter = $request->validate([
            'nama' => ['nullable', 'string', 'max:80'],
            'kelas' => ['nullable', 'string', 'max:80'],
            // Parameter lama (pencarian gabungan nama/kelas) tetap diterima
            // supaya tautan atau bookmark lama tidak error.
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $queryPetugas = User::where('role', 'teller');

        $kataKunciNama = trim((string) ($filter['nama'] ?? ''));
        $kataKunciKelas = trim((string) ($filter['kelas'] ?? ''));
        $kataKunciGabungan = trim((string) ($filter['q'] ?? ''));

        // Filter cari: nama petugas.
        if ($kataKunciNama !== '') {
            $queryPetugas->where('name', 'like', "%{$kataKunciNama}%");
        }

        // Filter cari: kelas petugas.
        if ($kataKunciKelas !== '') {
            $queryPetugas->where('kelas', 'like', "%{$kataKunciKelas}%");
        }

        // Pencarian cepat satu kolom (nama ATAU kelas) bila nama/kelas tidak diisi.
        if ($kataKunciNama === '' && $kataKunciKelas === '' && $kataKunciGabungan !== '') {
            $queryPetugas->where(function ($subQuery) use ($kataKunciGabungan) {
                $subQuery->where('name', 'like', "%{$kataKunciGabungan}%")
                    ->orWhere('kelas', 'like', "%{$kataKunciGabungan}%");
            });
        }

        return $queryPetugas->orderBy('name');
    }

    public function officerIndex(Request $request)
    {
        $petugas = $this->officerQuery($request)->paginate(20)->withQueryString();

        return view('supervisor.petugas', ['petugas' => $petugas]);
    }
}