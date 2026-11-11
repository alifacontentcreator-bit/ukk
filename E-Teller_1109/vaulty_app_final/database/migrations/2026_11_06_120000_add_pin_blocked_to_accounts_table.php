<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 120000 add pin blocked to accounts table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status blokir PIN pada rekening.
 *
 * Diisi true setelah nasabah salah PIN 3 kali. Akun tetap bisa login dan
 * mengakses aplikasi (agar bisa mengajukan buka blokir), tetapi transaksi
 * diblokir sampai Admin menyetujui pengajuan buka blokir + reset PIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('accounts', 'pin_blocked')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->boolean('pin_blocked')->default(false)->after('pin_reset_required');
            });
        }

        // Rekening yang sudah terkunci PIN sebelum kolom ini ada tetap harus
        // dianggap diblokir agar alur buka blokir + reset PIN berlaku.
        \Illuminate\Support\Facades\DB::table('accounts')
            ->where('pin_reset_required', true)
            ->update(['pin_blocked' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'pin_blocked')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('pin_blocked');
            });
        }
    }
};
