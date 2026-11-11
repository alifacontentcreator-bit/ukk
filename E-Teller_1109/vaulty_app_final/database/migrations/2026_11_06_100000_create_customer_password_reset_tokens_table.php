<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 11 06 100000 create customer password reset tokens table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode OTP untuk nasabah yang lupa password. Kode dikirim ke email yang
 * terdaftar pada akun (data pengguna di Admin) dan hanya disimpan sebagai hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_password_reset_tokens')) {
            return;
        }

        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->char('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
    }
};
