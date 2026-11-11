<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 000003 create duty weekly schedules table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Template jadwal jaga mingguan (Senin–Jumat).
 *
 * Jadwal disimpan per hari dalam minggu, bukan per tanggal, sehingga
 * Supervisor cukup mengaturnya sekali dan jadwal otomatis berlaku
 * setiap minggu sampai diubah kembali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('duty_weekly_schedules')) {
            return;
        }

        Schema::create('duty_weekly_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('duty_shift_id')
                ->constrained('duty_shifts')
                ->cascadeOnDelete();

            // Teller yang bertugas.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 1 = Senin ... 7 = Minggu (ISO-8601).
            $table->unsignedTinyInteger('day_of_week');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['day_of_week', 'duty_shift_id', 'user_id'],
                'duty_weekly_unique'
            );
            $table->index('day_of_week');
        });

        $this->pindahkanJadwalLama();
    }

    /**
     * Memindahkan jadwal bertanggal yang masih berlaku (hari ini dan setelahnya)
     * menjadi template mingguan, supaya jadwal lama tidak hilang.
     */
    private function pindahkanJadwalLama(): void
    {
        if (!Schema::hasTable('duty_schedules')) {
            return;
        }

        $rows = DB::table('duty_schedules')
            ->whereDate('duty_date', '>=', today()->toDateString())
            ->orderBy('duty_date')
            ->get(['duty_shift_id', 'user_id', 'duty_date', 'created_by']);

        foreach ($rows as $row) {
            DB::table('duty_weekly_schedules')->insertOrIgnore([
                'day_of_week' => (int) \Carbon\Carbon::parse($row->duty_date)->dayOfWeekIso,
                'duty_shift_id' => $row->duty_shift_id,
                'user_id' => $row->user_id,
                'created_by' => $row->created_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_weekly_schedules');
    }
};
