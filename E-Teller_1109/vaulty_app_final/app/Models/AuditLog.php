<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * Kolom dasar yang selalu dipakai. Kolom tambahan (module, status, dsb.)
     * diisi lewat forceFill oleh TransactionActivityLogger setelah dipastikan
     * kolomnya ada, supaya tetap kompatibel dengan skema lama/uji minimal.
     */
    protected $fillable = [
        'user_id', 'action', 'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
