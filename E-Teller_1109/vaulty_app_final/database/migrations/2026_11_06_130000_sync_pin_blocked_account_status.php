<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 130000 sync pin blocked account status; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan rekening yang sudah ditandai pin_blocked (dari versi
 * sebelumnya) agar status rekeningnya juga menjadi "blocked".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')
            ->where('pin_blocked', true)
            ->where('status', 'active')
            ->update(['status' => 'blocked']);
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis: status blokir adalah keputusan operasional.
    }
};
