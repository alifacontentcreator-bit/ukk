<?php
/* Tujuan file: Model ini merepresentasikan data report notification di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi untuk Teller berisi keputusan Supervisor atas laporan
 * kas harian yang dikirim (disetujui atau ditolak).
 */
class ReportNotification extends Model
{
    protected $fillable = [
        'user_id',
        'daily_report_id',
        'title',
        'message',
        'type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    /**
     * Jumlah notifikasi yang belum dibaca oleh pengguna tertentu.
     */
    public static function unreadCountFor(int $userId): int
    {
        return static::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
