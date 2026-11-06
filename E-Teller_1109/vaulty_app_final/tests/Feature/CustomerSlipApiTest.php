<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slip nasabah diambil dari transaksi final yang dicatat teller.
 */
class CustomerSlipApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Account $account;
    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-slip',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Irfan Cahyo',
            'username' => '17750',
            'nis' => '17750',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->account = Account::create([
            'user_id' => $this->customer->id,
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
    }

    private function createTransaction(
        string $type,
        float $amount,
        string $reference,
        float $before,
        float $after,
        string $status = 'success'
    ): Transaction {
        return Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-' . $reference,
            'reference_number' => $reference,
            'type' => $type,
            'amount' => $amount,
            'status' => $status,
            'description' => $type === 'deposit' ? 'Setor Tunai' : 'Tarik Tunai',
            'approved_at' => now(),
            'data_before' => ['balance' => $before],
            'data_after' => ['balance' => $after],
        ]);
    }

    public function test_slip_list_contains_teller_transactions(): void
    {
        $deposit = $this->createTransaction('deposit', 10000, 'REF261106AAAAAA', 45000, 55000);
        $this->createTransaction('withdrawal', 5000, 'REF261106BBBBBB', 55000, 50000);
        $this->createTransaction('deposit', 1000, 'REF-PENDING', 50000, 51000, 'pending');

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/slips?type=deposit')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', (string) $deposit->id)
            ->assertJsonPath('data.0.reference_number', 'REF261106AAAAAA')
            ->assertJsonPath('data.0.type', 'deposit')
            ->assertJsonPath('data.0.type_label', 'Setoran')
            ->assertJsonPath('data.0.amount', 10000)
            ->assertJsonPath('data.0.amount_formatted', 'Rp10.000')
            ->assertJsonStructure([
                'data' => [['issued_at', 'slip_number', 'status']],
                'meta' => ['next_cursor', 'per_page'],
            ]);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/slips')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_slip_detail_and_pdf_use_official_format(): void
    {
        $transaction = $this->createTransaction(
            'deposit',
            150000,
            'REF261106CCCCCC',
            10000,
            160000
        );

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/slips/' . $transaction->id)
            ->assertOk()
            ->assertJsonPath('data.reference_number', 'REF261106CCCCCC')
            ->assertJsonPath('data.customer_name', 'Irfan Cahyo')
            ->assertJsonPath('data.account_number', 'TKJ-2026-01-02')
            ->assertJsonPath('data.teller_name', 'Budi Teller')
            ->assertJsonPath('data.type_label', 'Setoran')
            ->assertJsonPath('data.amount_formatted', 'Rp150.000')
            ->assertJsonPath('data.balance_before', 10000)
            ->assertJsonPath('data.balance_after', 160000)
            ->assertJsonPath('data.purpose', 'Tabungan Siswa')
            ->assertJsonPath('data.amount_in_words', 'Seratus lima puluh ribu rupiah')
            ->assertJsonPath(
                'data.verification_code',
                fn ($code) => is_string($code) && strlen($code) === 14
            );

        $response = $this->actingAs($this->customer, 'sanctum')
            ->get('/api/v1/slips/' . $transaction->id . '/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $bytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $bytes);
        $this->assertStringContainsString('BANK MINI', $bytes);
        $this->assertStringContainsString('Slip resmi', $bytes);
        $this->assertStringEndsWith('%%EOF', $bytes);
    }

    public function test_customer_cannot_read_another_customers_slip(): void
    {
        $other = User::create([
            'name' => 'Nasabah Lain',
            'username' => '18880',
            'nis' => '18880',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        $otherAccount = Account::create([
            'user_id' => $other->id,
            'account_number' => 'TKJ-2026-01-09',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'balance' => 0,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);
        $otherTransaction = Transaction::create([
            'account_id' => $otherAccount->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-OTHER',
            'reference_number' => 'REF-OTHER',
            'type' => 'deposit',
            'amount' => 5000,
            'status' => 'success',
            'approved_at' => now(),
        ]);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/slips/' . $otherTransaction->id)
            ->assertNotFound();

        $this->actingAs($this->customer, 'sanctum')
            ->get('/api/v1/slips/' . $otherTransaction->id . '/pdf')
            ->assertNotFound();
    }
}
