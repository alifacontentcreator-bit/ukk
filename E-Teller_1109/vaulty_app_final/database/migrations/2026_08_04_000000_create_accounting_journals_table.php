<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 08 04 000000 create accounting journals table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->string('account_code', 10); // 111 = Kas, 211 = Hutang Tabungan Nasabah, dst.
            $table->enum('position', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journals');
    }
};
