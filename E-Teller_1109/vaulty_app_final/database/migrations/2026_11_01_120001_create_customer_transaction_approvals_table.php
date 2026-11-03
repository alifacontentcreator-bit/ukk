<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_transaction_approvals')) {
            Schema::create('customer_transaction_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('teller_id')->constrained('users');
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->string('type', 20);
                $table->decimal('amount', 15, 2);
                $table->string('description')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasIndex('customer_transaction_approvals', 'cta_account_status_expiry_idx')) {
            Schema::table('customer_transaction_approvals', function (Blueprint $table) {
                $table->index(
                    ['account_id', 'status', 'expires_at'],
                    'cta_account_status_expiry_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transaction_approvals');
    }
};
