<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 10 01 000002 add address to users table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'alamat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('alamat', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'alamat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('alamat');
            });
        }
    }
};