<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifikasi keputusan Supervisor atas laporan kas harian Teller.
     *
     * Tabel terpisah dari `customer_notifications` karena penerimanya
     * adalah petugas (Teller), bukan nasabah.
     */
    public function up(): void
    {
        if (Schema::hasTable('report_notifications')) {
            return;
        }

        Schema::create('report_notifications', function (Blueprint $table) {
            $table->id();

            // Teller penerima notifikasi.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Laporan harian yang disetujui / ditolak.
            $table->foreignId('daily_report_id')
                ->nullable()
                ->constrained('daily_reports')
                ->nullOnDelete();

            $table->string('title', 150);
            $table->text('message');

            // report_approved | report_rejected
            $table->string('type', 30)->default('report');

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_notifications');
    }
};
