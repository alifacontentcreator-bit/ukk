<?php

namespace App\Services;

use App\Models\DutyWeeklySchedule;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * =============================================================================
 * FILE INI UNTUK: Logika TUKAR SHIFT & "kunci" jadwal efektif Teller.
 * =============================================================================
 *
 * Inti logika:
 * - Setiap Teller hanya boleh bertransaksi pada hari dia PUNYA shift.
 * - Teller A yang berhalangan mengajukan tukar shift (menunjuk Teller B).
 * - Setelah Supervisor menyetujui, jadwal EFEKTIF berubah otomatis:
 *     • A tidak lagi punya shift pada tanggal itu (tidak bisa transaksi).
 *     • B mendapat shift tersebut (bisa transaksi).
 *
 * Semua pengecekan "hari ini dia bertugas atau tidak" memakai jadwalEfektif().
 */
class ShiftSwapService
{
    public function __construct(private PetugasNotifier $notifier)
    {
    }

    /**
     * Jadwal EFEKTIF seorang Teller pada tanggal tertentu
     * (sudah memperhitungkan tukar shift yang disetujui).
     *
     * @return Collection<int, object>
     */
    public function jadwalEfektif(User $teller, CarbonInterface $tanggal): Collection
    {
        $hariKerja = $tanggal->dayOfWeekIso;

        // Sabtu & Minggu tidak ada jadwal jaga.
        if ($hariKerja > DutyWeeklySchedule::HARI_TERAKHIR) {
            return collect();
        }

        // 1) Jadwal normal dari template mingguan (shift aktif saja).
        $jadwalNormal = DutyWeeklySchedule::with('shift')
            ->where('user_id', $teller->id)
            ->where('day_of_week', $hariKerja)
            ->get()
            ->filter(fn ($baris) => $baris->shift && $baris->shift->is_active);

        // 2) Shift yang sudah DITUKARKAN ke orang lain pada tanggal ini.
        $shiftDitukarKeluar = ShiftSwapRequest::approved()
            ->whereDate('duty_date', $tanggal)
            ->where('requester_id', $teller->id)
            ->pluck('duty_shift_id')
            ->all();

        $jadwal = $jadwalNormal
            ->reject(fn ($baris) => in_array($baris->duty_shift_id, $shiftDitukarKeluar))
            ->map(fn ($baris) => (object) [
                'shift' => $baris->shift,
                'duty_date' => $tanggal->copy(),
                'pengganti' => false,
            ])
            ->values();

        // 3) Shift yang DIAMBIL ALIH sebagai teller pengganti.
        $swapMasuk = ShiftSwapRequest::approved()
            ->with('shift')
            ->whereDate('duty_date', $tanggal)
            ->where('substitute_id', $teller->id)
            ->get();

        foreach ($swapMasuk as $swap) {
            if (!$swap->shift) {
                continue;
            }

            $jadwal->push((object) [
                'shift' => $swap->shift,
                'duty_date' => $tanggal->copy(),
                'pengganti' => true,
            ]);
        }

        // Hindari shift ganda yang sama pada satu tanggal.
        return $jadwal->unique(fn ($item) => (int) $item->shift->id)->values();
    }

    /** Apakah Teller bertugas (punya shift efektif) pada tanggal tertentu. */
    public function bertugasPada(User $teller, CarbonInterface $tanggal): bool
    {
        return $this->jadwalEfektif($teller, $tanggal)->isNotEmpty();
    }

    /** Apakah Teller bertugas hari ini (dipakai untuk membuka akses transaksi). */
    public function bertugasHariIni(User $teller): bool
    {
        return $this->bertugasPada($teller, today());
    }

    /**
     * Apakah hari ini sistem sudah punya jadwal jaga untuk hari tersebut?
     * Bila jadwal belum diatur sama sekali, penjagaan tidak memblokir Teller
     * (menghindari sistem terkunci saat data jadwal belum disiapkan).
     */
    public function jadwalHariIniSudahDiatur(): bool
    {
        return DutyWeeklySchedule::where('day_of_week', today()->dayOfWeekIso)->exists();
    }

    /** Apakah Teller hari ini bertugas sebagai PENGGANTI (menggantikan rekan). */
    public function penggantiHariIni(User $teller): ?ShiftSwapRequest
    {
        return ShiftSwapRequest::approved()
            ->with(['requester', 'shift'])
            ->whereDate('duty_date', today())
            ->where('substitute_id', $teller->id)
            ->first();
    }

    /** Alasan Teller tidak boleh mengajukan tukar shift (null = boleh). */
    public function alasanTidakBolehAjukan(User $teller): ?string
    {
        $masihAda = ShiftSwapRequest::where('requester_id', $teller->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('created_at', '>=', now()->subDays(ShiftSwapRequest::JEDA_MINIMAL_HARI))
            ->latest()
            ->first();

        if ($masihAda) {
            return 'Anda sudah mengajukan tukar shift dalam 7 hari terakhir (status: ' .
                $masihAda->statusLabel() . '). Tukar shift hanya boleh 1 kali dalam 7 hari.';
        }

        return null;
    }

    /**
     * Teller mengajukan tukar shift.
     *
     * @param  array{tanggal: string, duty_shift_id: int, substitute_id: int, reason: string}  $data
     */
    public function ajukan(User $pemohon, array $data): ShiftSwapRequest
    {
        if ($alasan = $this->alasanTidakBolehAjukan($pemohon)) {
            throw new DomainException($alasan);
        }

        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        $pilihanTanggal = ShiftSwapRequest::tanggalYangBolehDipilih();

        // Tanggal harus termasuk rentang yang diizinkan (H+2 s.d. H+9, hari kerja).
        $bolehDipilih = collect($pilihanTanggal)->contains(fn ($pilihan) => $pilihan->isSameDay($tanggal));

        if (!$bolehDipilih) {
            throw new DomainException(
                'Tanggal tukar shift harus antara ' .
                $pilihanTanggal[0]->translatedFormat('d F Y') . ' sampai ' .
                collect($pilihanTanggal)->last()->translatedFormat('d F Y') .
                ' (hari kerja, minimal 2 hari dari sekarang).'
            );
        }

        $idShift = (int) $data['duty_shift_id'];

        // Teller hanya bisa menukar shift yang memang dijadwalkan untuknya.
        $terjadwal = DutyWeeklySchedule::where('user_id', $pemohon->id)
            ->where('day_of_week', $tanggal->dayOfWeekIso)
            ->where('duty_shift_id', $idShift)
            ->exists();

        if (!$terjadwal) {
            throw new DomainException('Anda tidak terjadwal pada shift tersebut di tanggal yang dipilih.');
        }

        $pengganti = User::where('role', 'teller')->where('id', $data['substitute_id'])->first();

        if (!$pengganti) {
            throw new DomainException('Petugas pengganti tidak valid.');
        }

        if ((int) $pengganti->id === (int) $pemohon->id) {
            throw new DomainException('Tidak bisa menunjuk diri sendiri sebagai pengganti.');
        }

        $swap = DB::transaction(fn () => ShiftSwapRequest::create([
            'requester_id' => $pemohon->id,
            'substitute_id' => $pengganti->id,
            'duty_date' => $tanggal->toDateString(),
            'duty_shift_id' => $idShift,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]));

        // Notifikasi darurat ke seluruh Supervisor & Admin.
        $this->notifier->kirimKeSupervisorDanAdmin(
            'Pengajuan tukar shift',
            $pemohon->name . ' (' . ($pemohon->kelas ?: 'tanpa kelas') . ') mengajukan tukar shift tanggal ' .
            $tanggal->translatedFormat('d F Y') . ' dan meminta ' . $pengganti->name .
            ' (' . ($pengganti->kelas ?: 'tanpa kelas') . ') sebagai pengganti. Alasan: ' . $data['reason'],
            'shift_swap_request'
        );

        return $swap;
    }

    /** Supervisor menyetujui → kunci transaksi pindah dari pemohon ke pengganti. */
    public function setujui(ShiftSwapRequest $swap, User $supervisor): void
    {
        if ($swap->status !== 'pending') {
            throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
        }

        $sudahAda = ShiftSwapRequest::approved()
            ->whereDate('duty_date', $swap->duty_date)
            ->where('requester_id', $swap->requester_id)
            ->where('id', '!=', $swap->id)
            ->exists();

        if ($sudahAda) {
            throw new DomainException('Sudah ada tukar shift yang disetujui untuk petugas ini pada tanggal tersebut.');
        }

        $swap->update([
            'status' => 'approved',
            'approved_by' => $supervisor->id,
            'approved_at' => now(),
        ]);

        $tanggal = $swap->duty_date->translatedFormat('d F Y');
        $namaShift = $swap->shift?->label() ?? 'shift jaga';

        // Beri tahu Teller A (pemohon) bahwa ia resmi libur pada tanggal itu.
        $this->notifier->kirimKe(
            $swap->requester_id,
            'Tukar shift disetujui',
            'Pengajuan tukar shift Anda pada ' . $tanggal . ' (' . $namaShift . ') disetujui. ' .
            $swap->substitute->name . ' akan menggantikan Anda. Anda tidak bertugas pada tanggal tersebut.',
            'shift_swap_approved'
        );

        // Beri tahu Teller B (pengganti): selamat bekerja.
        $this->notifier->kirimKe(
            $swap->substitute_id,
            'Selamat bekerja!',
            'Anda adalah teller pengganti pada ' . $tanggal . ' (' . $namaShift . '), menggantikan ' .
            $swap->requester->name . '. Menu transaksi Anda sudah terbuka pada tanggal tersebut.',
            'shift_swap_approved'
        );
    }

    /** Supervisor menolak pengajuan. */
    public function tolak(ShiftSwapRequest $swap, User $supervisor, string $alasan): void
    {
        if ($swap->status !== 'pending') {
            throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
        }

        $swap->update([
            'status' => 'rejected',
            'approved_by' => $supervisor->id,
            'approved_at' => now(),
            'rejected_reason' => $alasan,
        ]);

        $this->notifier->kirimKe(
            $swap->requester_id,
            'Tukar shift ditolak',
            'Pengajuan tukar shift Anda pada ' . $swap->duty_date->translatedFormat('d F Y') .
            ' ditolak Supervisor. Alasan: ' . $alasan,
            'shift_swap_rejected'
        );
    }
}
