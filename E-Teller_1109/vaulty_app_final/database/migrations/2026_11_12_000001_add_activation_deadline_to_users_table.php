<?php
/* Tujuan file: Migrasi ini menambah tenggat aktivasi akun nasabah (60 hari) pada tabel users; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Jendela aktivasi akun nasabah baru, dihitung sejak akun dibuat. */
    private const WINDOW_DAYS = 60;

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'activation_deadline')) {
                $table->timestamp('activation_deadline')
                    ->nullable()
                    ->after('password_changed_at');
            }
        });

        if (
            !Schema::hasColumn('users', 'activation_deadline')
            || !Schema::hasColumn('users', 'must_change_password')
        ) {
            return;
        }

        /*
         * Akun nasabah yang belum pernah diaktivasi lewat aplikasi mobile
         * (must_change_password = true) kini berstatus NONAKTIF sampai nasabah
         * menyelesaikan aktivasi. Tenggat aktivasi 60 hari dihitung dari
         * tanggal akun dibuat. Akun TIDAK dihapus baik sebelum maupun sesudah
         * tenggat; statusnya tetap nonaktif selama belum ada aktivasi.
         */
        DB::table('users')
            ->where('role', 'customer')
            ->where('must_change_password', true)
            ->whereNull('activation_deadline')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'activation_deadline' => Carbon::parse($user->created_at ?? now())
                            ->addDays(self::WINDOW_DAYS),
                    ]);
                }
            });

        DB::table('users')
            ->where('role', 'customer')
            ->where('must_change_password', true)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'activation_deadline')) {
                $table->dropColumn('activation_deadline');
            }
        });
    }
};
