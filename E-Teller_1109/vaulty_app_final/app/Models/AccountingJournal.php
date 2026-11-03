<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingJournal extends Model
{
    protected $fillable = [
        'transaction_id', 'account_code', 'position', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
