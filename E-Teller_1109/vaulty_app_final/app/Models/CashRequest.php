<?php
/* Tujuan file: Model ini merepresentasikan data cash request di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan tambah modal dari Teller kepada Supervisor.
 *
 * Teller hanya mengajukan permintaan (tanpa nominal); Supervisor yang
 * menentukan jumlah modal tambahan yang diserahkan dari brankas.
 */
class CashRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'teller_id',
        'request_date',
        'status',
        'note',
        'cash_transfer_id',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'request_date' => 'date',
        'handled_at' => 'datetime',
    ];

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function cashTransfer(): BelongsTo
    {
        return $this->belongsTo(CashTransfer::class);
    }

    /**
     * Jumlah permintaan tambah modal yang belum diproses.
     */
    public static function pendingCount(): int
    {
        if (!Schema::hasTable('cash_requests')) {
            return 0;
        }

        return static::where('status', self::STATUS_PENDING)->count();
    }
}
