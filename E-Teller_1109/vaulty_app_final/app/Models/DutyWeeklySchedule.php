<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Template jadwal jaga mingguan.
 *
 * Satu baris = satu petugas pada satu shift di satu hari dalam minggu.
 * Karena tidak memakai tanggal, jadwal otomatis berlaku setiap minggu
 * (bisa dipakai sebulan/tahunan) sampai Supervisor mengubahnya.
 */
class DutyWeeklySchedule extends Model
{
    use HasFactory;

    /**
     * Daftar hari yang dipakai pada halaman Jadwal Jaga.
     *
     * Hanya Senin–Jumat karena jadwal masuk sekolah berakhir hari Jumat;
     * Sabtu dan Minggu tidak dijadwalkan.
     *
     * @var array<int, string>
     */
    public const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
    ];

    /**
     * Hari terakhir yang memiliki jadwal jaga (Jumat).
     */
    public const HARI_TERAKHIR = 5;

    protected $fillable = [
        'duty_shift_id',
        'user_id',
        'day_of_week',
        'created_by',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(DutyShift::class, 'duty_shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Nama hari pada template ini.
     */
    public function dayLabel(): string
    {
        return self::HARI[$this->day_of_week] ?? '-';
    }

    /**
     * Jadwal template yang berlaku pada tanggal tertentu.
     *
     * Hasilnya diberi atribut tambahan `duty_date` agar tampilan
     * (absensi Teller/Supervisor) tetap bisa menampilkan tanggalnya.
     *
     * @return Collection<int, self>
     */
    public static function forDate(CarbonInterface $date, ?int $userId = null): Collection
    {
        // Sabtu & Minggu tidak ada jadwal jaga (sekolah libur).
        if ($date->dayOfWeekIso > self::HARI_TERAKHIR) {
            return collect();
        }

        return static::with(['shift', 'user'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->where('day_of_week', $date->dayOfWeekIso)
            ->get()
            ->filter(fn (self $row) => $row->shift)
            ->each(fn (self $row) => $row->setAttribute('duty_date', $date->copy()))
            ->values();
    }

    /**
     * Jadwal template dengan shift aktif saja pada tanggal tertentu.
     *
     * @return Collection<int, self>
     */
    public static function forActiveDate(CarbonInterface $date, ?int $userId = null): Collection
    {
        return static::forDate($date, $userId)
            ->filter(fn (self $row) => (bool) $row->shift?->is_active)
            ->values();
    }
}
