<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat transaksi mobile harus berasal dari catatan teller
 * (endpoint GET /api/v1/transactions), urut terbaru lebih dulu.
 */
class CustomerTransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_teller_transactions_latest_first(): void
    {
        $teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-riwayat',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Irfan Cahyo',
            'username' => '17750',
            'nis' => '17750',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $account = Account::create([
            'user_id' => $customer->id,
            'account_number' => 'TKJ-2026-01-02',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'pin_attempts' => 0,
            'pin_reset_required' => false,
            'pin_blocked' => false,
            'balance' => 55000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        $deposit = Transaction::create([
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-RIWAYAT01',
            'reference_number' => 'REF261106AAAAAA',
            'type' => 'deposit',
            'amount' => 10000,
            'status' => 'success',
            'description' => 'Setor Tunai',
            'approved_at' => now()->subMinutes(5),
            'data_after' => ['balance' => 55000],
        ]);

        $withdrawal = Transaction::create([
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-RIWAYAT02',
            'reference_number' => 'REF261106BBBBBB',
            'type' => 'withdrawal',
            'amount' => 5000,
            'status' => 'success',
            'description' => 'Tarik Tunai',
            'approved_at' => now(),
            'data_after' => ['balance' => 50000],
        ]);

        // Transaksi yang belum final tidak boleh tampil di riwayat nasabah.
        Transaction::create([
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-RIWAYAT03',
            'reference_number' => 'REF261106CCCCCC',
            'type' => 'deposit',
            'amount' => 1000,
            'status' => 'pending',
            'approved_at' => null,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/transactions?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $withdrawal->id)
            ->assertJsonPath('data.0.type', 'withdrawal')
            ->assertJsonPath('data.0.type_label', 'Penarikan')
            ->assertJsonPath('data.0.signed_amount', -5000)
            ->assertJsonPath('data.1.id', $deposit->id)
            ->assertJsonPath('data.1.reference_number', 'REF261106AAAAAA')
            ->assertJsonPath('data.1.type_label', 'Setoran')
            ->assertJsonPath('data.1.signed_amount', 10000)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_history_remains_visible_while_pin_is_blocked(): void
    {
        $teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-riwayat-blokir',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Irfan Cahyo',
            'username' => '17751',
            'nis' => '17751',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $account = Account::create([
            'user_id' => $customer->id,
            'account_number' => 'TKJ-2026-01-03',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'pin_blocked' => true,
            'balance' => 10000,
            'product_type' => 'tabungan_siswa',
            'status' => 'blocked',
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-RIWAYAT-BLK',
            'reference_number' => 'REF261106DDDDDD',
            'type' => 'deposit',
            'amount' => 5000,
            'status' => 'success',
            'approved_at' => now(),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/transactions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.type', 'deposit');
    }
}
