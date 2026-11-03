<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'no_telp')) {
                $table->string('no_telp', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'no_telp')) {
                $table->dropColumn('no_telp');
            }
            if (Schema::hasColumn('users', 'email')) {
                $table->dropUnique('users_email_unique');
                $table->dropColumn('email');
            }
        });
    }
};
