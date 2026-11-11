<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario transaction approval flow test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomerTransactionApproval;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur lengkap permintaan transaksi dari Teller sampai saldo nasabah berubah:
 * Teller menyimpan -> aplikasi nasabah menerima notifikasi (no. ref, jenis,
 * nominal, teller, waktu) -> nasabah konfirmasi dengan PIN -> transaksi dicatat
 * -> Teller menerima status berhasil.
 */
class TransactionApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_alur_setoran_dari_teller_sampai_saldo_bertambah(): void
    {
        $teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-flow',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $nasabah = User::create([
            'name' => 'Irfan Cahyo',
            'username' => 'irfan-flow',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $account = Account::create([
            'user_id' => $nasabah->id,
            'account_number' => 'FLOW-0001',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'balance' => 20000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        // 1. Teller menyimpan setoran: masih menunggu konfirmasi nasabah.
        $this->actingAs($teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'deposit',
                'amount' => 5000,
            ])
            ->assertRedirect()
            ->assertSessionHas('customer_transaction_approval_id');

        $approval = CustomerTransactionApproval::firstOrFail();

        $this->assertSame('pending', $approval->status);
        $this->assertNotEmpty($approval->reference_number);
        // Saldo belum berubah sebelum nasabah menyetujui.
        $this->assertSame(20000.0, (float) $account->fresh()->balance);

        // 2. Aplikasi nasabah menerima notifikasi berisi nomor referensi,
        //    jenis transaksi, nominal, nama Teller, dan waktu pengajuan.
        $this->actingAs($nasabah, 'sanctum')
            ->getJson('/api/v1/transactions/pending-approval')
            ->assertOk()
            ->assertJsonPath('data.0.id', $approval->id)
            ->assertJsonPath('data.0.reference_number', $approval->reference_number)
            ->assertJsonPath('data.0.type', 'deposit')
            ->assertJsonPath('data.0.type_label', 'Setoran Tunai')
            ->assertJsonPath('data.0.amount', 5000)
            ->assertJsonPath('data.0.teller_name', 'Budi Teller')
            ->assertJsonStructure(['data' => [['requested_at', 'expires_at']]]);

        // 3. Nasabah memasukkan PIN lalu menekan Konfirmasi Transaksi.
        $this->actingAs($nasabah, 'sanctum')
            ->postJson(
                "/api/v1/transactions/pending-approval/{$approval->id}/authorize",
                ['pin' => '246810']
            )
            ->assertOk()
            ->assertJsonPath('code', 'TRANSACTION_APPROVED')
            ->assertJsonPath('transaction.balance', 25000);

        $this->assertSame(25000.0, (float) $account->fresh()->balance);

        $transaction = Transaction::firstOrFail();
        $this->assertSame($approval->reference_number, $transaction->reference_number);
        $this->assertSame('success', $transaction->status);

        // 4. Halaman teller menerima status berhasil + referensi + tautan slip.
        $this->actingAs($teller)
            ->getJson(route('teller.transaksi.approval-status', $approval->id))
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('reference_number', $approval->reference_number)
            ->assertJsonPath('transaction_id', $transaction->id);
    }
}
