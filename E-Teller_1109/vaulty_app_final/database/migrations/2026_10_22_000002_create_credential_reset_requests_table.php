<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 10 22 000002 create credential reset requests table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credential_reset_requests')) {
            return;
        }

        Schema::create('credential_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('pin');          // pin | password
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('verification_note')->nullable();
            $table->string('status', 20)->default('pending');    // pending | approved | rejected | expired | cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_reset_requests');
    }
};