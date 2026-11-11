<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 10 31 084032 create daily report reopen logs table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel ini sudah dibuat oleh migrasi
        // 2026_10_31_000002_add_report_reopening_audit. Guard ini
        // menjaga `php artisan migrate:fresh` tetap berjalan pada
        // instalasi baru tanpa error "table already exists".
        if (Schema::hasTable('daily_report_reopen_logs')) {
            return;
        }

        Schema::create('daily_report_reopen_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_report_id')
                ->constrained('daily_reports')
                ->cascadeOnDelete();

            $table->foreignId('supervisor_id')
                ->constrained('users');

            $table->string('reason', 500);

            $table->timestamp('reopened_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_reopen_logs');
    }
};
