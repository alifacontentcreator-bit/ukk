<?php
/* Tujuan file: Factory ini menyediakan data contoh duty shift factory untuk kebutuhan pengujian atau pembuatan data awal. */


namespace Database\Factories;

use App\Models\DutyShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutyShift>
 */
class DutyShiftFactory extends Factory
{
    protected $model = DutyShift::class;

    /**
     * Shift jaga default (07:00–09:30) yang aktif.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Shift ' . fake()->unique()->numberBetween(1, 99),
            'start_time' => '07:00:00',
            'end_time' => '09:30:00',
            'is_active' => true,
        ];
    }

    /** Shift nonaktif (untuk menguji penolakan absensi). */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
