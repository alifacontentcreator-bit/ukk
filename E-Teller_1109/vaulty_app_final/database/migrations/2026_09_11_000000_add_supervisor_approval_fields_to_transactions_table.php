<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 09 11 000000 add supervisor approval fields to transactions table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom-kolom yang dibutuhkan alur persetujuan Teller -> Supervisor.
     * Sebelumnya kolom-kolom ini sudah dipakai di SupervisorController & tampilan,
     * tapi belum pernah dibuat di tabel `transactions`, sehingga transaksi tidak
     * pernah benar-benar berstatus "pending" menunggu Supervisor.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->unique()->after('transaction_code');
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejected_reason', 500)->nullable()->after('approved_at');
            $table->json('data_before')->nullable()->after('rejected_reason');
            $table->json('data_after')->nullable()->after('data_before');
        });

        // Perluas status untuk alur persetujuan dengan default pending.
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'success', 'corrected', 'void'])
                ->default('pending')
                ->change();
        });

        // Backfill data lama: isi reference_number dari transaction_code supaya tidak NULL semua.
        DB::table('transactions')->whereNull('reference_number')->update([
            'reference_number' => DB::raw('transaction_code'),
        ]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['reference_number', 'approved_at', 'rejected_reason', 'data_before', 'data_after']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'success', 'corrected', 'void'])
                ->default('success')
                ->change();
        });
    }
};
