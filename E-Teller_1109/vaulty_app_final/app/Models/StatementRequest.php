<?php
/* Tujuan file: Model ini merepresentasikan permintaan mutasi rekening (statement request) yang diajukan
   nasabah dari aplikasi mobile lalu diproses Teller menjadi berkas PDF. */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StatementRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'request_code', 'account_id', 'user_id', 'period_from', 'period_to',
        'status', 'file_path', 'note', 'processed_by', 'processed_at',
        'notified_at', 'expires_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'processed_at' => 'datetime',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function account() { return $this->belongsTo(Account::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }

    /** Pending DAN belum kedaluwarsa. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function (Builder $builder) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => $this->isExpired() ? 'Kedaluwarsa' : 'Menunggu Petugas',
            self::STATUS_READY => 'Siap Diunduh',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            default => $this->status,
        };
    }

    /** Contoh: 01/11/2026 s/d 11/11/2026 */
    public function periodLabel(): string
    {
        return $this->period_from->format('d/m/Y') . ' s/d ' . $this->period_to->format('d/m/Y');
    }

    public function fileName(): string
    {
        $accountNumber = preg_replace(
            '/[^A-Za-z0-9\-_]/',
            '',
            (string) $this->account?->account_number
        );

        if (!$accountNumber) {
            return 'mutasi-' . $this->request_code . '.pdf';
        }

        return 'mutasi-' . $accountNumber . '-'
            . $this->period_from->format('Ymd') . '-'
            . $this->period_to->format('Ymd') . '.pdf';
    }

    /** Kode unik permintaan mutasi. */
    public static function generateCode(): string
    {
        do {
            $code = 'MUT' . now()->format('ymd') . strtoupper(Str::random(6));
        } while (self::where('request_code', $code)->exists());

        return $code;
    }
}
