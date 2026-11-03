<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teller_id')->constrained('users');
            $table->date('report_date');

            // Saldo awal & rincian lembaran fisik kas laci teller
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->unsignedInteger('pecahan_100k')->default(0);
            $table->unsignedInteger('pecahan_50k')->default(0);
            $table->unsignedInteger('pecahan_20k')->default(0);

            // Rekap transaksi sistem hari itu
            $table->decimal('deposit_total', 15, 2)->default(0);
            $table->decimal('withdrawal_total', 15, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->decimal('expected_cash', 15, 2)->default(0); // Net setoran - penarikan menurut sistem

            // Status verifikasi Supervisor
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Status pemindahan kas ke Brankas Utama
            $table->enum('transfer_status', ['pending', 'approved'])->default('pending');
            $table->foreignId('transferred_by')->nullable()->constrained('users');
            $table->timestamp('transferred_at')->nullable();

            $table->timestamps();

            $table->unique(['teller_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
