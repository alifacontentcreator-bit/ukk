<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung aplikasi mobile nasabah:
     * - accounts : penghitung PIN salah, waktu kunci, dan waktu terakhir PIN diganti
     * - users    : penanda wajib ganti password (dipakai jalur reset lewat loket nanti)
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'pin_attempts')) {
                $table->unsignedTinyInteger('pin_attempts')->default(0)->after('pin_is_default');
            }
            if (!Schema::hasColumn('accounts', 'pin_locked_until')) {
                $table->timestamp('pin_locked_until')->nullable()->after('pin_attempts');
            }
            if (!Schema::hasColumn('accounts', 'pin_changed_at')) {
                $table->timestamp('pin_changed_at')->nullable()->after('pin_locked_until');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            foreach (['pin_attempts', 'pin_locked_until', 'pin_changed_at'] as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['must_change_password', 'password_changed_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};