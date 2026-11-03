<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('users');

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action', 100);
            $table->string('module', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('method', 20)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('email')->nullable()->unique();
            $table->string('no_telp', 20)->nullable();
            $table->string('nis', 30)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('alamat')->nullable();
            $table->string('kelas', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('account_number', 30);
            $table->string('pin');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('product_type', 30)->default('tabungan_siswa');
            $table->string('qr_token', 80)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('user_id');
            $table->string('transaction_code')->unique();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('success');
            $table->timestamps();
        });
    }

    public function test_customer_with_transaction_history_cannot_be_deleted(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Lama', '12345', 'customer');

        $accountId = $this->createAccount($customer);

        DB::table('transactions')->insert([
            'account_id' => $accountId,
            'user_id' => $admin->id,
            'transaction_code' => 'TRX-TEST-0001',
            'type' => 'deposit',
            'amount' => 50000,
            'status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $customer));

        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString(
            'riwayat transaksi rekening',
            session('errors')->first('error')
        );

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('accounts', ['id' => $accountId]);
        $this->assertDatabaseHas('transactions', ['transaction_code' => 'TRX-TEST-0001']);
    }

    public function test_teller_with_served_transactions_cannot_be_deleted(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah', '54321', 'customer');
        $teller = $this->createUser('Teller Lama', 'teller01', 'teller');

        $accountId = $this->createAccount($customer);

        DB::table('transactions')->insert([
            'account_id' => $accountId,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-TEST-0002',
            'type' => 'deposit',
            'amount' => 25000,
            'status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $teller));

        $response->assertSessionHasErrors('error');
        $this->assertStringContainsString(
            'transaksi yang dilayani sebagai teller',
            session('errors')->first('error')
        );

        $this->assertDatabaseHas('users', ['id' => $teller->id]);
    }

    public function test_user_without_operational_history_can_be_deleted(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Baru', '99999', 'customer');

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $customer));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');

        $response = $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function createUser(string $name, string $username, string $role): User
    {
        return User::create([
            'name' => $name,
            'username' => $username,
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function createAccount(User $customer): int
    {
        return DB::table('accounts')->insertGetId([
            'user_id' => $customer->id,
            'account_number' => 'TKJ-2024-01-01',
            'pin' => bcrypt('123456'),
            'balance' => 0,
            'product_type' => 'tabungan_siswa',
            'qr_token' => 'test-qr-token',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
