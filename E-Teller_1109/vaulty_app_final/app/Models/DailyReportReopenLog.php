<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReportReopenLog extends Model
{
    protected $table = 'daily_report_reopen_logs';

    protected $fillable = [
        'daily_report_id',
        'supervisor_id',
        'reason',
        'reopened_at',
    ];

    protected $casts = [
        'daily_report_id' => 'integer',
        'supervisor_id' => 'integer',
        'reopened_at' => 'datetime',
    ];

    /**
     * Relasi ke laporan harian yang dibuka kembali.
     */
    public function report()
    {
        return $this->belongsTo(
            DailyReport::class,
            'daily_report_id'
        );
    }

    /**
     * Relasi ke Supervisor yang membuka kembali sesi.
     */
    public function supervisor()
    {
        return $this->belongsTo(
            User::class,
            'supervisor_id'
        );
    }
}
