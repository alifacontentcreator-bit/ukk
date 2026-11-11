<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario supervisor journal history test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jurnal Audit Supervisor wajib memuat arsip transaksi lama (dari dulu sampai
 * sekarang) tanpa filter tanggal, sehingga data historis tidak hilang.
 */
class SupervisorJournalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_jurnal_supervisor_menampilkan_transaksi_lama(): void
    {
        $supervisor = User::create([
            'name' => 'Bapak Supervisor',
            'username' => 'spv-jurnal',
            'password' => 'password',
            'role' => 'supervisor',
            'status' => 'active',
        ]);

        $teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-jurnal',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Nasabah Alumni',
            'username' => '12345',
            'nis' => '12345',
            'kelas' => 'XII TKJ 1',
            'angkatan' => now()->year - 4,
            'password' => 'password',
            'role' => 'customer',
            'status' => 'blocked',
        ]);

        $account = Account::create([
            'user_id' => $customer->id,
            'account_number' => 'TKJ-2022-01-01',
            'pin' => bcrypt('123456'),
            'pin_is_default' => false,
            'pin_attempts' => 0,
            'pin_reset_required' => false,
            'pin_blocked' => false,
            'balance' => 75000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        $transaction = new Transaction([
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-LAMA-0001',
            'reference_number' => 'REF-LAMA-0001',
            'type' => 'deposit',
            'amount' => 75000,
            'status' => 'approved',
            'description' => 'Setoran lama sebelum nasabah lulus',
            'approved_at' => now()->subYears(3),
            'data_after' => ['balance' => 75000],
        ]);
        // Transaksi dari tiga tahun lalu: harus tetap tampil di jurnal.
        $transaction->created_at = now()->subYears(3);
        $transaction->updated_at = now()->subYears(3);
        $transaction->save();

        $this->actingAs($supervisor)
            ->get(route('supervisor.jurnal'))
            ->assertOk()
            ->assertSee('Jurnal Transaksi')
            ->assertSee('REF-LAMA-0001')
            ->assertSee('Nasabah Alumni')
            ->assertSee('arsip sejak');

        // Arsip tidak berubah setelah dibuka supervisor.
        $this->assertDatabaseHas('transactions', ['transaction_code' => 'TRX-LAMA-0001']);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'blocked']);
    }
}
