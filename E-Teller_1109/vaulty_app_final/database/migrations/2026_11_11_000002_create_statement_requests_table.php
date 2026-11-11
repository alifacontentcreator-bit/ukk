<?php
/* Tujuan file: Migrasi ini membuat tabel permintaan mutasi rekening (statement) yang diajukan nasabah
   dari aplikasi mobile dan diproses Teller, termasuk penyimpanan berkas PDF hasil cetak mutasi. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('statement_requests')) {
            return;
        }

        Schema::create('statement_requests', function (Blueprint $table) {
            $table->id();

            // Kode permintaan yang ditampilkan ke nasabah & teller (contoh: MUT261111ABCDEF).
            $table->string('request_code', 40)->unique();

            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Periode mutasi yang diminta (inklusif).
            $table->date('period_from');
            $table->date('period_to');

            // pending  : menunggu diproses Teller
            // ready    : berkas PDF sudah dibuat & dikirim ke nasabah
            // rejected : ditolak Teller (alasan pada kolom note)
            // expired  : tidak diproses melewati batas waktu
            $table->string('status', 20)->default('pending');

            $table->string('file_path')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_requests');
    }
};
