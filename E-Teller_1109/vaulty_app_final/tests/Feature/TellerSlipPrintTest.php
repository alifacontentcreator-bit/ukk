<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario teller slip print test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slip cetak teller memakai format resmi BANK MINI lengkap dengan watermark.
 */
class TellerSlipPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_slip_print_shows_official_fields_and_watermark(): void
    {
        $teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-cetak',
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
            'balance' => 55000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        $transaction = Transaction::create([
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'transaction_code' => 'TRX-CETAK01',
            'reference_number' => 'REF261106SLIP01',
            'type' => 'deposit',
            'amount' => 10000,
            'status' => 'success',
            'description' => 'Setor Tunai',
            'approved_at' => now(),
        ]);

        $this->actingAs($teller)
            ->get(route('teller.transaksi.cetak', $transaction->id))
            ->assertOk()
            ->assertSee('LKM MITRA SISWA ABADI')
            ->assertSee('BANK MINI')
            ->assertSee('No. Referensi')
            ->assertSee('REF261106SLIP01')
            ->assertSee('No. Rekening')
            ->assertSee('TKJ-2026-01-02')
            ->assertSee('Irfan Cahyo')
            ->assertSee('Jenis Transaksi')
            ->assertSee('Setoran Tunai')
            ->assertSee('Jumlah Tunai')
            ->assertSee('Rp 10.000')
            ->assertSee('Nama Teller')
            ->assertSee('Budi Teller')
            ->assertSee('watermark BANK MINI');
    }
}
