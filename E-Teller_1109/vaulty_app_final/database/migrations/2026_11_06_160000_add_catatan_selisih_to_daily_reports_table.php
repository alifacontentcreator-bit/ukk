<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 160000 add catatan selisih to daily reports table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan Teller atas selisih kas.
     *
     * Rumus kas seharusnya = saldo awal + setoran - penarikan. Selisih =
     * uang fisik - kas seharusnya. Bila selisih tidak nol, Teller wajib
     * menulis catatan sebagai penjelasan sebelum laporan dikirim.
     */
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('daily_reports', 'catatan_selisih')) {
                return;
            }

            $table->text('catatan_selisih')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('daily_reports', 'catatan_selisih')) {
                $table->dropColumn('catatan_selisih');
            }
        });
    }
};
