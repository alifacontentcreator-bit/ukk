<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan transaksi hari itu yang dipilih Teller sebagai penjelasan
     * selisih kas pada laporan harian.
     *
     * Kasus nyata: Teller menerima modal, lalu di hari itu hanya ada
     * penarikan tunai (tanpa setoran) sehingga uang di laci lebih kecil
     * daripada modal. Agar selisih tersebut dapat dipertanggungjawabkan,
     * laporan mencatat transaksi mana yang menyebabkan pengurangan kas.
     */
    public function up(): void
    {
        Schema::create('daily_report_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_report_id')
                ->constrained('daily_reports')
                ->cascadeOnDelete();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->timestamps();

            // Satu transaksi hanya boleh dipilih sekali per laporan.
            $table->unique(['daily_report_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_transactions');
    }
};
