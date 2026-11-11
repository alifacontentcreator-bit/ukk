<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 140000 add small denominations to daily reports table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian lembaran laporan harian Teller kini juga menampung pecahan
     * kecil: Rp10.000, Rp2.000, Rp1.000, Rp500, Rp100, dan Rp50.
     *
     * Tanpa kolom ini hitungan uang fisik di laci tidak bisa pas ketika
     * laci berisi pecahan kecil (mis. uang kembalian), sehingga laporan
     * selalu berselisih dengan kas akhir sistem.
     */
    public function up(): void
    {
        $daftar = [
            // Uang fisik kas akhir
            'pecahan_10k',
            'pecahan_2k',
            'pecahan_1k',
            'pecahan_500',
            'pecahan_100',
            'pecahan_50',

            // Rincian lembaran saldo awal (opsional)
            'pecahan_awal_10k',
            'pecahan_awal_2k',
            'pecahan_awal_1k',
            'pecahan_awal_500',
            'pecahan_awal_100',
            'pecahan_awal_50',
        ];

        $baru = array_values(array_filter(
            $daftar,
            fn (string $kolom) => !Schema::hasColumn('daily_reports', $kolom)
        ));

        if ($baru === []) {
            return;
        }

        Schema::table('daily_reports', function (Blueprint $table) use ($baru) {
            foreach ($baru as $kolom) {
                $table->unsignedInteger($kolom)->default(0);
            }
        });
    }

    public function down(): void
    {
        $ada = array_values(array_filter(
            [
                'pecahan_10k',
                'pecahan_2k',
                'pecahan_1k',
                'pecahan_500',
                'pecahan_100',
                'pecahan_50',
                'pecahan_awal_10k',
                'pecahan_awal_2k',
                'pecahan_awal_1k',
                'pecahan_awal_500',
                'pecahan_awal_100',
                'pecahan_awal_50',
            ],
            fn (string $kolom) => Schema::hasColumn('daily_reports', $kolom)
        ));

        if ($ada !== []) {
            Schema::table('daily_reports', function (Blueprint $table) use ($ada) {
                $table->dropColumn($ada);
            });
        }
    }
};
