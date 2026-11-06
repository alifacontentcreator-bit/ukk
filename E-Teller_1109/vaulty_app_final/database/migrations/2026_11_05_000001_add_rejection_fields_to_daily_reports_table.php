<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supervisor perlu dapat menolak laporan kas harian Teller ketika
     * uang fisik di laci tidak sesuai dengan catatan sistem (selisih /
     * minus). Jejak penolakan disimpan agar Teller tahu alasan laporan
     * harus direvisi.
     *
     * Setiap kolom diperiksa lebih dahulu supaya migrasi tetap aman
     * dijalankan pada basis data yang sudah pernah ditambah manual.
     */
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_reports', 'rejected_reason')) {
                $table->string('rejected_reason', 500)
                    ->nullable()
                    ->after('approved_at');
            }

            if (!Schema::hasColumn('daily_reports', 'rejected_by')) {
                $table->foreignId('rejected_by')
                    ->nullable()
                    ->after('rejected_reason')
                    ->constrained('users');
            }

            if (!Schema::hasColumn('daily_reports', 'rejected_at')) {
                $table->timestamp('rejected_at')
                    ->nullable()
                    ->after('rejected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('daily_reports', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            $columns = [];

            if (Schema::hasColumn('daily_reports', 'rejected_reason')) {
                $columns[] = 'rejected_reason';
            }

            if (Schema::hasColumn('daily_reports', 'rejected_at')) {
                $columns[] = 'rejected_at';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
