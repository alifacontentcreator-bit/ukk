<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CredentialResetRequest extends Model
{
    //Konstanta jenis permintaan
    public const TYPE_PIN = 'pin';
    public const TYPE_PASSWORD = 'password';

    //Konstanta status permintaan

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    //menentukan kolom yang boleh diisi menggunakan fitur mass assignment Laravel.

    protected $fillable = [
        'account_id', 'user_id', 'type', 'requested_by', 'reason', 'verification_note',
        'status', 'reviewed_by', 'reviewed_at', 'review_note', 'expires_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    //satu permintaan reset dimiliki oleh satu rekening.

    public function account() { return $this->belongsTo(Account::class); }
    //Menghubungkan permintaan reset dengan pemilik akun.
    public function user() { return $this->belongsTo(User::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }

    /** Pending DAN belum kedaluwarsa. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => $this->isExpired() ? 'Kedaluwarsa' : 'Menunggu Admin',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_EXPIRED => 'Kedaluwarsa',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => $this->status,
        };
    }
}