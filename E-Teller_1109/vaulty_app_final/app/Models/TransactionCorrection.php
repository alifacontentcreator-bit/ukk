<?php
/* Tujuan file: Model ini merepresentasikan data transaction correction di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionCorrection extends Model
{
    use HasFactory;

    // Mengizinkan semua kolom diisi
    protected $guarded = [];

    protected $casts = [
        'corrected_amount' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function teller()
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}