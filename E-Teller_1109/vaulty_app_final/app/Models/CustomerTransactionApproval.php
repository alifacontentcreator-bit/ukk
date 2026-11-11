<?php
/* Tujuan file: Model ini merepresentasikan data customer transaction approval di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTransactionApproval extends Model
{
    protected $fillable = [
        'account_id',
        'teller_id',
        'transaction_id',
        'reference_number',
        'type',
        'amount',
        'description',
        'status',
        'expires_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function teller()
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
