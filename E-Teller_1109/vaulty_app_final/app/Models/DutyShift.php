<?php
/* Tujuan file: Model ini merepresentasikan data duty shift di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DutyShift extends Model
{
    use HasFactory;

    // Tolerance minutes before shift start that still allows check‑in
    public const TOLERANCE_MINUTES = 15;

    protected $fillable = ['name', 'start_time', 'end_time', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function schedules() { return $this->hasMany(DutySchedule::class); }

    public function weeklySchedules() { return $this->hasMany(DutyWeeklySchedule::class); }

    /**
     * Catatan kehadiran yang memakai shift ini.
     * Dipakai untuk menjaga riwayat absensi agar tidak hilang saat shift dihapus.
     */
    public function attendances() { return $this->hasMany(DutyAttendance::class); }

    /** Contoh: "Shift 1 (07:00-09:30)". */
    public function label(): string
    {
        return $this->name . ' (' . substr($this->start_time, 0, 5) . '-' . substr($this->end_time, 0, 5) . ')';
    }

    /**
     * Jendela absen masuk untuk shift pada tanggal tertentu.
     *
     * Absen masuk dibuka TOLERANCE_MINUTES sebelum jam shift dan ditutup
     * saat jam shift selesai. Contoh shift 07:00-09:30 → jendela 06:45-09:30.
     *
     * @return array{open: CarbonInterface, close: CarbonInterface}
     */
    public static function windowFor(DutyShift $shift, ?CarbonInterface $date = null): array
    {
        $tanggal = $date ? $date->copy() : today();

        return [
            'open'  => $tanggal->copy()->setTimeFromTimeString($shift->start_time)->subMinutes(self::TOLERANCE_MINUTES),
            'close' => $tanggal->copy()->setTimeFromTimeString($shift->end_time),
        ];
    }

    /** Label jendela absen masuk, contoh "06:45-09:30". */
    public static function windowLabel(DutyShift $shift, ?CarbonInterface $date = null): string
    {
        $window = self::windowFor($shift, $date);

        return $window['open']->format('H:i') . '-' . $window['close']->format('H:i');
    }

    /**
     * Determine if the current time is within the allowed check‑in window for this shift.
     * The window is (start_time - TOLERANCE_MINUTES) up to end_time.
     * If end_time is earlier than start_time the window is considered closed.
     * Returns array with keys 'allowed' (bool) and 'reason' (string).
     */
    public static function checkInWindow(DutyShift $shift): array
    {
        $now = now();
        ['open' => $start, 'close' => $end] = self::windowFor($shift);

        if (!$shift->is_active) {
            return ['allowed' => false, 'reason' => 'Shift tidak aktif'];
        }
        // If end time is before or equal to start (after tolerance) the window is invalid
        if ($end->lessThanOrEqualTo($start)) {
            return ['allowed' => false, 'reason' => 'Jendela absensi tidak valid'];
        }
        if ($now->lt($start)) {
            return ['allowed' => false, 'reason' => 'Absen dibuka pukul ' . $start->format('H:i')];
        }
        if ($now->gt($end)) {
            return ['allowed' => false, 'reason' => 'Absen tutup pukul ' . $end->format('H:i')];
        }
        return ['allowed' => true, 'reason' => ''];
    }
}
