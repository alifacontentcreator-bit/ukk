<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekap cetak laporan harian Teller memuat dua rincian lembaran:
     * saldo awal (modal yang diterima) dan uang fisik kas akhir.
     *
     * Nominal Rp5.000 ditambahkan karena rekap kas sering berakhir pada
     * kelipatan lima ribu. Rincian saldo awal bersifat opsional: bila
     * Teller tidak menghitungnya, cetakan memakai total modal sistem.
     */
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            foreach ([
                'pecahan_5k' => 'pecahan_20k',
                'pecahan_awal_100k' => 'pecahan_5k',
                'pecahan_awal_50k' => 'pecahan_awal_100k',
                'pecahan_awal_20k' => 'pecahan_awal_50k',
                'pecahan_awal_5k' => 'pecahan_awal_20k',
            ] as $column => $after) {
                if (Schema::hasColumn('daily_reports', $column)) {
                    continue;
                }

                $table->unsignedInteger($column)
                    ->default(0)
                    ->after(Schema::hasColumn('daily_reports', $after) ? $after : 'pecahan_20k');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['pecahan_5k', 'pecahan_awal_100k', 'pecahan_awal_50k', 'pecahan_awal_20k', 'pecahan_awal_5k'],
                fn (string $column) => Schema::hasColumn('daily_reports', $column)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
