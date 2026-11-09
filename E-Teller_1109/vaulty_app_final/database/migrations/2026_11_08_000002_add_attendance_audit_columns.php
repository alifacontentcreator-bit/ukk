<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom jejak audit pada duty_attendances:
 * - previous_status : nilai status sebelum diubah Supervisor
 * - updated_by      : id Supervisor yang mengubah status (berbeda dari marked_by yang membuat record)
 *
 * Catatan: kolom marked_by sudah ada di migration awal (2026_10_30_000001).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('duty_attendances', function (Blueprint $table) {
            $table->string('previous_status', 15)->nullable()->after('status');
            $table->foreignId('updated_by')->nullable()->after('marked_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('duty_attendances', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['previous_status', 'updated_by']);
        });
    }
};
