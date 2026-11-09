<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pengajuan TUKAR SHIFT jaga antar Teller.
     *
     * Alur: Teller A (berhalangan) mengajukan tukar shift untuk tanggal tertentu
     * dan menunjuk Teller B sebagai pengganti. Bila Supervisor menyetujui,
     * hak "kunci" transaksi pada tanggal itu berpindah dari A ke B.
     */
    public function up(): void
    {
        if (Schema::hasTable('shift_swap_requests')) {
            return;
        }

        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();

            // Teller A = yang meminta izin (tidak bisa jaga).
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();

            // Teller B = pengganti yang ditunjuk.
            $table->foreignId('substitute_id')->constrained('users')->cascadeOnDelete();

            // Tanggal shift yang ditukar + shiftnya (bila ada).
            $table->date('duty_date');
            $table->foreignId('duty_shift_id')->nullable()->constrained('duty_shifts')->nullOnDelete();

            $table->string('reason', 255);

            // pending | approved | rejected | cancelled
            $table->string('status', 20)->default('pending');

            // Jejak keputusan Supervisor.
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['duty_date', 'status']);
            $table->index(['requester_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');
    }
};
