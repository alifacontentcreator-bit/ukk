<?php
/* Tujuan file: Factory ini menyediakan data contoh duty weekly schedule factory untuk kebutuhan pengujian atau pembuatan data awal. */


namespace Database\Factories;

use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutyWeeklySchedule>
 */
class DutyWeeklyScheduleFactory extends Factory
{
    protected $model = DutyWeeklySchedule::class;

    /**
     * Satu baris template jadwal mingguan: satu teller pada satu shift di satu hari.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'duty_shift_id' => DutyShift::factory(),
            'user_id' => User::factory()->teller(),
            'day_of_week' => 1,
            'created_by' => null,
        ];
    }
}
