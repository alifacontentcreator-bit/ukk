<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 01 120000 add pin reset required to accounts table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'pin_reset_required')) {
                $table->boolean('pin_reset_required')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'pin_reset_required')) {
                $table->dropColumn('pin_reset_required');
            }
        });
    }
};
