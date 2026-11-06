<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teller boleh menerima modal lebih dari satu kali dalam sehari:
 * modal awal di pagi hari, lalu tambahan modal ketika kas laci menipis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_transfers')) {
            return;
        }

        // Index biasa dibuat lebih dahulu: MySQL memakai index unique lama
        // untuk foreign key teller_id, jadi tidak boleh dihapus sebelum ada pengganti.
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->index(
                ['teller_id', 'type', 'transfer_date'],
                'cash_transfer_teller_type_date_index'
            );
        });

        // Unique lama: satu Teller hanya boleh punya satu baris per jenis per tanggal.
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->dropUnique('cash_transfer_teller_type_date_unique');
        });
    }

    public function down(): void
    {
        // Unique dibuat kembali sebelum index biasa dihapus (kebutuhan foreign key).
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->unique(
                ['teller_id', 'type', 'transfer_date'],
                'cash_transfer_teller_type_date_unique'
            );
        });

        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->dropIndex('cash_transfer_teller_type_date_index');
        });
    }
};
