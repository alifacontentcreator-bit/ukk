<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutyShift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function schedules() { return $this->hasMany(DutySchedule::class); }

    /** Contoh: "Shift 1 (07:00-09:30)". */
    public function label(): string
    {
        return $this->name . ' (' . substr($this->start_time, 0, 5) . '-' . substr($this->end_time, 0, 5) . ')';
    }
}