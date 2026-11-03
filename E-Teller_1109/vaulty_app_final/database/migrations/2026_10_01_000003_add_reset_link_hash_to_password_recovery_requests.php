<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('password_recovery_requests', 'reset_token_hash')) {
            Schema::table('password_recovery_requests', function (Blueprint $table) {
                $table->char('reset_token_hash', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('password_recovery_requests', 'reset_token_hash')) {
            Schema::table('password_recovery_requests', function (Blueprint $table) {
                $table->dropUnique('password_recovery_requests_reset_token_hash_unique');
                $table->dropColumn('reset_token_hash');
            });
        }
    }
};