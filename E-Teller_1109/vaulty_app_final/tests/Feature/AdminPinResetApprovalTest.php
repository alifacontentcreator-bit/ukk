<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CredentialResetRequest;
use App\Models\User;
use App\Services\PinResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPinResetApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'customer_notifications', 'credential_reset_requests', 'accounts', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('account_number');
            $table->string('pin');
            $table->boolean('pin_is_default')->default(false);
            $table->unsignedTinyInteger('pin_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->boolean('pin_reset_required')->default(false);
            $table->timestamp('pin_changed_at')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('credential_reset_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->unsignedBigInteger('requested_by');
            $table->text('reason');
            $table->text('verification_note')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('route')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('status')->default('success');
            $table->timestamps();
        });
    }

    public function test_admin_approval_resets_pin_and_notifies_customer(): void
    {
        $customerId = $this->createUser('Customer', 'customer', 'customer');
        $tellerId = $this->createUser('Teller', 'teller', 'teller');
        $adminId = $this->createUser('Admin', 'admin', 'admin');
        $accountId = DB::table('accounts')->insertGetId([
            'user_id' => $customerId,
            'account_number' => 'RPL-2026-01-01',
            'pin' => Hash::make('135792'),
            'pin_is_default' => false,
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requestId = DB::table('credential_reset_requests')->insertGetId([
            'account_id' => $accountId,
            'user_id' => $customerId,
            'type' => CredentialResetRequest::TYPE_PIN,
            'requested_by' => $tellerId,
            'reason' => 'Nasabah terkunci setelah tiga kali salah PIN.',
            'verification_note' => 'Kartu pelajar dan wajah telah dicocokkan.',
            'status' => CredentialResetRequest::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $approved = app(PinResetService::class)->approve(
            CredentialResetRequest::findOrFail($requestId),
            User::findOrFail($adminId)
        );

        $this->assertTrue($approved);
        $account = Account::findOrFail($accountId);
        $this->assertTrue(Hash::check('123456', $account->pin));
        $this->assertTrue($account->pin_is_default);
        $this->assertFalse($account->pin_reset_required);
        $this->assertSame(0, $account->pin_attempts);
        $this->assertDatabaseHas('credential_reset_requests', [
            'id' => $requestId,
            'status' => CredentialResetRequest::STATUS_APPROVED,
            'reviewed_by' => $adminId,
        ]);
        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $customerId,
            'title' => 'PIN rekening direset',
        ]);
    }

    private function createUser(string $name, string $username, string $role): int
    {
        return DB::table('users')->insertGetId([
            'name' => $name,
            'username' => $username,
            'password' => 'unused',
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}