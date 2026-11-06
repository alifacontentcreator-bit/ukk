<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'account_number', 'pin', 'pin_is_default', 'balance', 'product_type', 'qr_token', 'status',
        'pin_attempts', 'pin_locked_until', 'pin_reset_required', 'pin_blocked', 'pin_changed_at',
    ];

    protected $hidden = ['pin'];

    protected $casts = [
        'balance' => 'decimal:2',
        'pin_is_default' => 'boolean',
        'pin_attempts' => 'integer',
        'pin_locked_until' => 'datetime',
        'pin_reset_required' => 'boolean',
        'pin_blocked' => 'boolean',
        'pin_changed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}