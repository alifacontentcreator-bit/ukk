<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 08 03 000000 create transactions table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')->constrained('accounts');   // Rekening nasabah
            $table->foreignId('user_id')->constrained('users');         // Teller yang melayani (avalis)
            $table->foreignId('daily_report_id')->nullable()->constrained('daily_reports')->nullOnDelete();

            $table->string('transaction_code')->unique();
            $table->enum('type', ['deposit', 'withdrawal']); // Setoran / Penarikan

            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();

            // pending: baru diinput, success: sudah final, corrected: sudah dikoreksi, void: dibatalkan
            $table->enum('status', ['pending', 'success', 'corrected', 'void'])->default('success');

            // Mitigasi refresh/putus koneksi saat 'Simpan' (idempotency key dari sisi client)
            $table->string('idempotency_key', 80)->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
