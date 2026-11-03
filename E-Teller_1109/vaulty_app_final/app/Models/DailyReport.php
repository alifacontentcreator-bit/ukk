<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'teller_id',
        'report_date',

        // Kas awal dan rincian uang fisik
        'saldo_awal',
        'pecahan_100k',
        'pecahan_50k',
        'pecahan_20k',

        // Rekap transaksi
        'deposit_total',
        'withdrawal_total',
        'transaction_count',
        'expected_cash',

        // Status laporan
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',

        // Status pemindahan kas
        'transfer_status',
        'transferred_by',
        'transferred_at',

        // Riwayat pengiriman laporan
        'submitted_at',

        // Riwayat pembukaan kembali sesi
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    protected $casts = [
        'report_date' => 'date',

        'saldo_awal' => 'decimal:2',

        'pecahan_100k' => 'integer',
        'pecahan_50k' => 'integer',
        'pecahan_20k' => 'integer',

        'deposit_total' => 'decimal:2',
        'withdrawal_total' => 'decimal:2',
        'expected_cash' => 'decimal:2',

        'approved_at' => 'datetime',
        'transferred_at' => 'datetime',

        'submitted_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    // Relasi dengan Teller
    public function teller()
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    // Relasi Supervisor yang menyetujui laporan
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Relasi Supervisor yang menerima kas
    public function transferApprover()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    // Relasi Supervisor yang membuka kembali sesi
    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    // Riwayat pembukaan kembali laporan
    public function reopenLogs()
    {
        return $this->hasMany(DailyReportReopenLog::class);
    }
}
