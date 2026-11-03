<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyAttendance extends Model
{
    /** Toleransi keterlambatan (menit) setelah jam mulai shift. */
    public const LATE_TOLERANCE = 10;

    public const STATUS = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];

    protected $fillable = [
        'user_id', 'duty_shift_id', 'duty_date', 'check_in_at', 'check_out_at',
        'status', 'note', 'marked_by',
    ];

    protected $casts = [
        'duty_date' => 'date',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function shift() { return $this->belongsTo(DutyShift::class, 'duty_shift_id'); }
    public function marker() { return $this->belongsTo(User::class, 'marked_by'); }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? ucfirst($this->status);
    }

    public function isLate(): bool
    {
        if (!$this->shift || !$this->check_in_at || $this->status !== 'hadir') {
            return false;
        }

        $limit = $this->check_in_at->copy()->setTimeFromTimeString($this->shift->start_time)->addMinutes(self::LATE_TOLERANCE);

        return $this->check_in_at->greaterThan($limit);
    }
}