<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel audit log.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            |
            | User yang melakukan aktivitas.
            | Nullable karena aktivitas tertentu bisa dilakukan oleh system.
            |
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | AKTIVITAS
            |--------------------------------------------------------------------------
            */

            $table->string('action', 100)
                ->index();

            $table->string('module', 100)
                ->nullable()
                ->index();

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | REQUEST / SISTEM
            |--------------------------------------------------------------------------
            */

            $table->string('method', 20)
                ->nullable();

            $table->string('route', 255)
                ->nullable();

            $table->ipAddress('ip_address')
                ->nullable()
                ->index();

            $table->text('user_agent')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | DATA SEBELUM & SESUDAH
            |--------------------------------------------------------------------------
            |
            | Digunakan jika suatu aktivitas mengubah data.
            | Contoh:
            | - Sebelum mengubah status transaksi
            | - Sesudah mengubah status transaksi
            |
            */

            $table->json('old_values')
                ->nullable();

            $table->json('new_values')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'success',
                'failed'
            ])->default('success')->index();

            /*
            |--------------------------------------------------------------------------
            | TIMESTAMP
            |--------------------------------------------------------------------------
            */

            $table->timestamps();

        });
    }


    /**
     * Menghapus tabel audit log.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};