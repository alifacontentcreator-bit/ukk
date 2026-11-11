<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 000002 create cash requests table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan tambah modal dari Teller kepada Supervisor.
 *
 * Teller tidak menentukan nominal; Supervisor yang memutuskan
 * jumlah modal tambahan yang diserahkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_requests')) {
            return;
        }

        Schema::create('cash_requests', function (Blueprint $table) {
            $table->id();

            // Teller yang mengajukan tambahan modal.
            $table->foreignId('teller_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('request_date');

            // pending = menunggu Supervisor, fulfilled = sudah diserahkan,
            // cancelled = dibatalkan / tidak diproses.
            $table->string('status', 20)->default('pending');

            // Catatan dari Teller (opsional), mis. "kas laci habis".
            $table->string('note', 500)->nullable();

            // Penyerahan modal yang memenuhi permintaan ini.
            $table->foreignId('cash_transfer_id')
                ->nullable()
                ->constrained('cash_transfers')
                ->nullOnDelete();

            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['teller_id', 'status']);
            $table->index(['status', 'request_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_requests');
    }
};
