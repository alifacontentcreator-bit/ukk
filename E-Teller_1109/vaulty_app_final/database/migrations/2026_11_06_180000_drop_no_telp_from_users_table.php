<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 180000 drop no telp from users table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor WhatsApp petugas sudah tidak dipakai lagi, sehingga kolom penyimpannya
 * ikut dihapus bersama formulir, impor, dan ekspor yang mengisinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'no_telp')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('no_telp');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'no_telp')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('no_telp', 20)->nullable();
            });
        }
    }
};
