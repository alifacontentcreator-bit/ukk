<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal jaga hanya Senin–Jumat karena jadwal masuk sekolah berakhir
     * hari Jumat. Baris jadwal hari Sabtu (6) dan Minggu (7) dihapus agar
     * tidak lagi muncul pada template mingguan maupun absensi.
     */
    public function up(): void
    {
        if (Schema::hasTable('duty_weekly_schedules')) {
            DB::table('duty_weekly_schedules')
                ->whereIn('day_of_week', [6, 7])
                ->delete();
        }

        /*
         * Tabel jadwal lama (berbasis tanggal) bila masih menyimpan data.
         * Filter dilakukan di PHP agar tetap jalan di MySQL maupun SQLite.
         */
        if (Schema::hasTable('duty_schedules')) {
            $weekendIds = DB::table('duty_schedules')
                ->get(['id', 'duty_date'])
                ->filter(fn ($row) => \Carbon\Carbon::parse($row->duty_date)->dayOfWeekIso > 5)
                ->pluck('id')
                ->all();

            if ($weekendIds !== []) {
                DB::table('duty_schedules')->whereIn('id', $weekendIds)->delete();
            }
        }
    }

    public function down(): void
    {
        // Jadwal Sabtu/Minggu tidak dikembalikan.
    }
};
