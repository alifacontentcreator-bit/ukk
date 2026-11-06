<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomerTransactionApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas saldo mengendap (default Rp 10.000) harus dicek Teller
 * SEBELUM permintaan otorisasi PIN dikirim ke nasabah.
 */
class TellerWithdrawalMinBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    private function buatRekening(float $saldo, string $nomor = 'TKJ-2026-01-02'): Account
    {
        $nasabah = User::create([
            'name' => 'Irfan Cahyo',
            'username' => 'irfan' . preg_replace('/\W/', '', $nomor),
            'password' => 'password',
            'role' => 'customer',
        ]);

        return Account::create([
            'user_id' => $nasabah->id,
            'account_number' => $nomor,
            'pin' => bcrypt('123456'),
            'pin_is_default' => false,
            'balance' => $saldo,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);
    }

    public function test_penarikan_ditolak_tanpa_meminta_pin_saat_saldo_hanya_saldo_mengendap(): void
    {
        $account = $this->buatRekening(10000);

        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'withdrawal',
                'amount' => 5000,
            ])
            ->assertSessionHasErrors('error');

        $pesan = (string) session('errors')->first('error');

        $this->assertStringContainsString('Transaksi gagal.', $pesan);
        $this->assertStringContainsString('Minimal saldo mengendap Rp 10.000', $pesan);

        // Tidak ada PIN yang diminta ke nasabah dan saldo tidak berubah.
        $this->assertDatabaseCount('customer_transaction_approvals', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(10000.0, (float) $account->fresh()->balance);
    }

    public function test_penarikan_ditolak_dan_menampilkan_maksimal_penarikan_yang_boleh(): void
    {
        $account = $this->buatRekening(12000);

        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'withdrawal',
                'amount' => 5000,
            ])
            ->assertSessionHasErrors('error');

        $pesan = (string) session('errors')->first('error');

        $this->assertStringContainsString('Minimal saldo mengendap Rp 10.000', $pesan);
        $this->assertStringContainsString('Maksimal penarikan untuk rekening ini Rp 2.000', $pesan);
        $this->assertDatabaseCount('customer_transaction_approvals', 0);
    }

    public function test_penarikan_yang_menyisakan_saldo_mengendap_tetap_meminta_pin_nasabah(): void
    {
        $account = $this->buatRekening(100000);

        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'withdrawal',
                'amount' => 50000,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('customer_transaction_approval_id');

        $this->assertDatabaseHas('customer_transaction_approvals', [
            'account_id' => $account->id,
            'teller_id' => $this->teller->id,
            'type' => 'withdrawal',
            'status' => 'pending',
            'amount' => 50000,
        ]);

        // Saldo baru berkurang setelah nasabah menyetujui lewat PIN.
        $this->assertSame(100000.0, (float) $account->fresh()->balance);
    }

    public function test_hanya_ada_satu_notifikasi_saat_menunggu_pin_nasabah(): void
    {
        $account = $this->buatRekening(100000);

        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'withdrawal',
                'amount' => 50000,
            ])
            ->assertSessionHas('customer_transaction_approval_id');

        $response = $this->actingAs($this->teller)->get(route('teller.transaksi.create', [
            'account_number' => $account->account_number,
            'type' => 'withdrawal',
        ]));

        $response->assertOk()
            // Hanya notifikasi hijau di atas yang tampil.
            ->assertSee('Permintaan transaksi #')
            // Penampung status tetap ada untuk polling, tetapi tersembunyi & kosong.
            ->assertSee('id="customerApprovalStatus"', false)
            ->assertSee('aria-live="polite" hidden', false)
            ->assertDontSee('Menunggu otorisasi nasabah di aplikasi');
    }

    public function test_halaman_transaksi_menampilkan_batas_penarikan_sesuai_saldo(): void
    {
        $account = $this->buatRekening(100000);

        $response = $this->actingAs($this->teller)->get(route('teller.transaksi.create', [
            'account_number' => $account->account_number,
        ]));

        $response->assertOk()
            ->assertSee('saldo mengendap')
            ->assertSee('Rp 10.000')
            ->assertSee('Maksimal penarikan untuk rekening ini')
            ->assertSee('Rp 90.000')
            // Notifikasi kuning duplikat tidak boleh muncul lagi.
            ->assertDontSee('Menunggu otorisasi nasabah di aplikasi');
    }

    public function test_setoran_juga_menunggu_konfirmasi_pin_nasabah_dan_saldo_belum_berubah(): void
    {
        $account = $this->buatRekening(20000);

        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'deposit',
                'amount' => 5000,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('customer_transaction_approval_id');

        $approval = CustomerTransactionApproval::firstOrFail();

        $this->assertSame('deposit', $approval->type);
        $this->assertSame('pending', $approval->status);
        $this->assertNotEmpty($approval->reference_number);
        $this->assertStringStartsWith('REF', (string) $approval->reference_number);

        // Saldo baru bertambah setelah nasabah menyetujui lewat PIN.
        $this->assertSame(20000.0, (float) $account->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transaksi_ditolak_saat_pin_nasabah_diblokir(): void
    {
        $account = $this->buatRekening(100000);
        $account->update([
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'pin_blocked' => true,
        ]);

        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $account->account_number,
                'type' => 'withdrawal',
                'amount' => 50000,
            ])
            ->assertSessionHasErrors('error');

        $pesan = (string) session('errors')->first('error');

        $this->assertStringContainsString('diblokir', $pesan);
        // Tidak ada permintaan PIN yang dikirim ke aplikasi nasabah.
        $this->assertDatabaseCount('customer_transaction_approvals', 0);
        $this->assertDatabaseCount('transactions', 0);
    }
}
