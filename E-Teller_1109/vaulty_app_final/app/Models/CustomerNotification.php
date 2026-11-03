<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerNotification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'message', 'type', 'transaction_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
}
