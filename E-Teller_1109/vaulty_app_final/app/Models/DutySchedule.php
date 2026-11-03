<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutySchedule extends Model
{
    protected $fillable = ['duty_shift_id', 'user_id', 'duty_date', 'created_by'];

    protected $casts = ['duty_date' => 'date'];

    public function shift() { return $this->belongsTo(DutyShift::class, 'duty_shift_id'); }
    public function user() { return $this->belongsTo(User::class); }
}