<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 10 31 091438 create cash transfers table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();

            // Teller penerima atau pengirim uang tunai
            $table->foreignId('teller_id')
                ->constrained('users');

            // Laporan harian terkait
            $table->foreignId('daily_report_id')
                ->nullable()
                ->constrained('daily_reports')
                ->nullOnDelete();

            // opening = Supervisor ke Teller
            // closing = Teller ke Supervisor
            $table->enum('type', ['opening', 'closing']);

            $table->date('transfer_date');

            // Nominal uang tunai
            $table->decimal('amount', 15, 2);

            // Status perpindahan uang
            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled'
            ])->default('pending');

            // Supervisor yang mencatat
            $table->foreignId('created_by')
                ->constrained('users');

            // Supervisor yang mengonfirmasi penyerahan
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users');

            $table->timestamp('confirmed_at')->nullable();

            $table->string('description', 500)->nullable();

            $table->timestamps();

            $table->unique(
                ['teller_id', 'type', 'transfer_date'],
                'cash_transfer_teller_type_date_unique'
            );

            $table->index(['transfer_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
