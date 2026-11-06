<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomerTransactionApproval;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PinService;
use App\Services\TransactionPostingService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class CustomerTransactionApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('customer_transaction_approvals');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status')->default('active');
            $table->string('email')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('account_number')->unique();
            $table->string('pin');
            $table->boolean('pin_is_default')->default(false);
            $table->unsignedTinyInteger('pin_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
            $table->boolean('pin_reset_required')->default(false);
            $table->boolean('pin_blocked')->default(false);
            $table->timestamp('pin_changed_at')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('product_type')->default('tabungan_siswa');
            $table->string('qr_token')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('customer_transaction_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('teller_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('reference_number', 40)->nullable();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('module', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('method', 20)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('status')->default('success');
            $table->timestamps();
        });

        Schema::create('customer_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 150);
            $table->text('message');
            $table->string('type', 30)->default('transaction');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_customer_can_reject_a_teller_request_without_changing_balance(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $tellerId = $this->createUser('teller', 'teller001');
        $approval = $this->createApproval($accountId, $tellerId, 'withdrawal');

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/transactions/pending-approval/{$approval->id}/reject")
            ->assertOk()
            ->assertJsonPath('code', 'TRANSACTION_REJECTED_BY_CUSTOMER');

        $this->assertDatabaseHas('customer_transaction_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('accounts', [
            'id' => $accountId,
            'balance' => 100000,
        ]);
    }

    public function test_customer_can_create_a_pin_without_a_default_pin(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        DB::table('accounts')->where('id', $accountId)->update(['pin_is_default' => true]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/pin/setup', [
                'new_pin' => '135792',
                'new_pin_confirmation' => '135792',
            ])
            ->assertOk()
            ->assertJsonPath('must_change_pin', false);

        $account = DB::table('accounts')->where('id', $accountId)->first();
        $this->assertTrue(Hash::check('135792', $account->pin));
        $this->assertFalse((bool) $account->pin_is_default);
        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $customer->id,
            'title' => 'PIN berhasil diperbarui',
        ]);
    }

    public function test_customer_cannot_use_pin_setup_after_a_pin_is_already_created(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $originalHash = DB::table('accounts')->where('id', $accountId)->value('pin');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/pin/setup', [
                'new_pin' => '135792',
                'new_pin_confirmation' => '135792',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PIN_ALREADY_SETUP');

        $this->assertSame(
            $originalHash,
            DB::table('accounts')->where('id', $accountId)->value('pin')
        );
    }

    public function test_third_wrong_pin_requires_a_reset_and_rejects_the_pending_request(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $tellerId = $this->createUser('teller', 'teller002');
        $approval = $this->createApproval($accountId, $tellerId, 'withdrawal');

        for ($attempt = 1; $attempt < PinService::MAX_ATTEMPTS; $attempt++) {
            $this->actingAs($customer, 'sanctum')
                ->postJson(
                    "/api/v1/transactions/pending-approval/{$approval->id}/authorize",
                    ['pin' => '000000']
                )
                ->assertUnprocessable()
                ->assertJsonPath('code', 'PIN_INVALID');
        }

        $this->actingAs($customer, 'sanctum')
            ->postJson(
                "/api/v1/transactions/pending-approval/{$approval->id}/authorize",
                ['pin' => '000000']
            )
            ->assertStatus(423)
            ->assertJsonPath('code', 'PIN_BLOCKED');

        $this->assertDatabaseHas('accounts', [
            'id' => $accountId,
            'pin_reset_required' => true,
            'pin_blocked' => true,
            'status' => 'blocked',
        ]);
        $this->assertDatabaseHas('customer_transaction_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
        ]);
    }

    public function test_correct_customer_pin_is_required_before_posting(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $tellerId = $this->createUser('teller', 'teller003');
        $approval = $this->createApproval($accountId, $tellerId, 'withdrawal');

        $this->mock(TransactionPostingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('post')
                ->once()
                ->andReturnUsing(function (
                    Account $account,
                    User $teller,
                    string $type,
                    float $amount
                ): Transaction {
                    $account->balance += $amount;
                    $account->save();

                    $transaction = new Transaction();
                    $transaction->id = 77;
                    $transaction->reference_number = 'REFTEST';

                    return $transaction;
                });
        });

        $this->actingAs($customer, 'sanctum')
            ->postJson(
                "/api/v1/transactions/pending-approval/{$approval->id}/authorize",
                ['pin' => '246810']
            )
            ->assertOk()
            ->assertJsonPath('code', 'TRANSACTION_APPROVED');

        $this->assertDatabaseHas('accounts', [
            'id' => $accountId,
            'balance' => 110000,
        ]);
        $this->assertDatabaseHas('customer_transaction_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'transaction_id' => 77,
        ]);
    }

    public function test_posting_failure_rejects_the_pending_request_without_changing_balance(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $tellerId = $this->createUser('teller', 'teller004');
        $approval = $this->createApproval($accountId, $tellerId);

        $this->mock(TransactionPostingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('post')
                ->once()
                ->andThrow(new DomainException('Kas Teller tidak mencukupi.'));
        });

        $this->actingAs($customer, 'sanctum')
            ->postJson(
                "/api/v1/transactions/pending-approval/{$approval->id}/authorize",
                ['pin' => '246810']
            )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'TRANSACTION_REJECTED');

        $this->assertDatabaseHas('accounts', [
            'id' => $accountId,
            'balance' => 100000,
        ]);
        $this->assertDatabaseHas('customer_transaction_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
        ]);
    }

    public function test_customer_must_enter_a_pin_to_approve_a_deposit(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $tellerId = $this->createUser('teller', 'teller005');
        $approval = $this->createApproval($accountId, $tellerId, 'deposit', 5000);

        $this->mock(TransactionPostingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('post')
                ->once()
                ->andReturnUsing(function (Account $account, User $teller, string $type, float $amount): Transaction {
                    $account->balance += $amount;
                    $account->save();
                    $transaction = new Transaction();
                    $transaction->id = 78;
                    $transaction->reference_number = 'REFDEPOSIT';

                    return $transaction;
                });
        });

        // Setoran tanpa PIN ditolak oleh validasi.
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/transactions/pending-approval/{$approval->id}/authorize")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pin');

        // Dengan PIN yang benar, setoran dicatat dan saldo bertambah.
        $this->actingAs($customer, 'sanctum')
            ->postJson(
                "/api/v1/transactions/pending-approval/{$approval->id}/authorize",
                ['pin' => '246810']
            )
            ->assertOk()
            ->assertJsonPath('code', 'TRANSACTION_APPROVED');

        $this->assertDatabaseHas('accounts', ['id' => $accountId, 'balance' => 105000]);
    }

    public function test_pending_approval_list_contains_reference_time_and_type(): void
    {
        [$customer, $accountId] = $this->createCustomer();
        $tellerId = $this->createUser('teller', 'teller006');
        $approval = $this->createApproval($accountId, $tellerId, 'deposit', 25000);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/transactions/pending-approval')
            ->assertOk()
            ->assertJsonPath('data.0.id', $approval->id)
            ->assertJsonPath('data.0.reference_number', $approval->reference_number)
            ->assertJsonPath('data.0.type', 'deposit')
            ->assertJsonPath('data.0.type_label', 'Setoran Tunai')
            ->assertJsonPath('data.0.amount', 25000)
            ->assertJsonPath('data.0.teller_name', 'Teller Test')
            ->assertJsonStructure([
                'data' => [[
                    'reference_number',
                    'type_label',
                    'requested_at',
                    'expires_at',
                ]],
            ]);
    }

    private function createCustomer(): array
    {
        $userId = $this->createUser('customer', 'customer001');
        $accountId = DB::table('accounts')->insertGetId([
            'user_id' => $userId,
            'account_number' => '120001000001',
            'pin' => Hash::make('246810'),
            'pin_is_default' => false,
            'pin_attempts' => 0,
            'pin_reset_required' => false,
            'balance' => 100000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [User::findOrFail($userId), $accountId];
    }

    private function createUser(string $role, string $username): int
    {
        return DB::table('users')->insertGetId([
            'name' => ucfirst($role) . ' Test',
            'username' => $username,
            'password' => 'unused',
            'role' => $role,
            'status' => 'active',
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createApproval(int $accountId, int $tellerId, string $type = 'deposit', int $amount = 10000): CustomerTransactionApproval
    {
        $id = DB::table('customer_transaction_approvals')->insertGetId([
            'account_id' => $accountId,
            'teller_id' => $tellerId,
            'reference_number' => 'REFTEST' . $accountId . strtoupper($type),
            'type' => $type,
            'amount' => $amount,
            'description' => 'Uji transaksi',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CustomerTransactionApproval::findOrFail($id);
    }
}
