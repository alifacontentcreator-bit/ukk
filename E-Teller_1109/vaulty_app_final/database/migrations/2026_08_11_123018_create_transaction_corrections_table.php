<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions'); // Transaksi yang dikoreksi
            $table->foreignId('teller_id')->constrained('users');             // Teller pengaju koreksi
            $table->foreignId('supervisor_id')->nullable()->constrained('users'); // Yang menyetujui

            // Setoran koreksi / Penarikan koreksi (sesuai kebutuhan wawancara)
            $table->enum('type', ['deposit_correction', 'withdrawal_correction']);
            $table->decimal('corrected_amount', 15, 2)->nullable(); // Nominal seharusnya
            $table->string('correction_code', 20)->nullable();      // Kode sandi koreksi yang diinput teller

            $table->text('reason'); // Alasan salah ketik/salah nominal/uang palsu-robek, dsb.
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_corrections');
    }
};
