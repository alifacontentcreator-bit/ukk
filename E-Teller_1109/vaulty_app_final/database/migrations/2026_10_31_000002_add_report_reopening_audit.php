<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')
                ->nullable()
                ->constrained('users');
            $table->string('reopen_reason', 500)->nullable();
        });

        Schema::create('daily_report_reopen_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_report_id')
                ->constrained('daily_reports');

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

        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn([
                'submitted_at',
                'reopened_at',
                'reopen_reason',
            ]);
        });
    }
};
