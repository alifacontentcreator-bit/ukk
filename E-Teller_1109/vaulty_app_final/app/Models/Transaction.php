<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id','user_id','daily_report_id','transaction_code','reference_number','type','amount',
        'description','status','idempotency_key','approved_by','approved_at','rejected_reason',
        'data_before','data_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'data_before' => 'array',
        'data_after' => 'array',
    ];

    public function account(){ return $this->belongsTo(Account::class); }
    public function teller(){ return $this->belongsTo(User::class, 'user_id'); }
    public function supervisor(){ return $this->belongsTo(User::class, 'approved_by'); }
}
