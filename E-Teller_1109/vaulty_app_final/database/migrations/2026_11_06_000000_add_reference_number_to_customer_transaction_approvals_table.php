<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 000000 add reference number to customer transaction approvals table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor referensi permintaan otorisasi nasabah.
 *
 * Nomor ini dibuat saat Teller menyimpan transaksi, ditampilkan pada
 * notifikasi di aplikasi nasabah, lalu dipakai kembali sebagai nomor
 * referensi transaksi ketika nasabah menyetujui dengan PIN. Dengan begitu
 * Teller dan nasabah melihat nomor yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customer_transaction_approvals', 'reference_number')) {
            Schema::table('customer_transaction_approvals', function (Blueprint $table) {
                $table->string('reference_number', 40)->nullable()->after('transaction_id');
                $table->index('reference_number', 'cta_reference_number_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_transaction_approvals', 'reference_number')) {
            Schema::table('customer_transaction_approvals', function (Blueprint $table) {
                $table->dropIndex('cta_reference_number_idx');
                $table->dropColumn('reference_number');
            });
        }
    }
};
