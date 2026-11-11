<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 09 11 000100 add rejected reason to transaction corrections table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_corrections', function (Blueprint $table) {
            $table->string('rejected_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_corrections', function (Blueprint $table) {
            $table->dropColumn('rejected_reason');
        });
    }
};
