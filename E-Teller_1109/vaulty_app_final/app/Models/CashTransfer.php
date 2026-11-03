<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransfer extends Model
{
    protected $table = 'cash_transfers';

    protected $fillable = [
        'teller_id',
        'daily_report_id',
        'type',
        'transfer_date',
        'amount',
        'status',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'description',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relasi Teller
    |--------------------------------------------------------------------------
    */

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi Laporan Harian
    |--------------------------------------------------------------------------
    */

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Supervisor yang Membuat Pencatatan
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Supervisor yang Mengonfirmasi Penyerahan Uang
    |--------------------------------------------------------------------------
    */

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scope Jenis Perpindahan Kas
    |--------------------------------------------------------------------------
    */

    public function scopeOpening($query)
    {
        return $query->where('type', 'opening');
    }

    public function scopeClosing($query)
    {
        return $query->where('type', 'closing');
    }

    /*
    |--------------------------------------------------------------------------
    | Scope Status Konfirmasi
    |--------------------------------------------------------------------------
    */

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
