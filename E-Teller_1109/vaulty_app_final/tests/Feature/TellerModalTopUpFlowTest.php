<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario teller modal top up flow test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashRequest;
use App\Models\CashTransfer;
use App\Models\DailyReport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur modal Teller: modal awal → kas habis dipakai penarikan →
 * Teller mengajukan tambah modal → Supervisor menentukan nominal →
 * Teller konfirmasi → laporan harian memakai total modal.
 */
class TellerModalTopUpFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::create([
            'name' => 'Bapak Supervisor',
            'username' => 'spv',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        $this->teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    private function setVault(float $amount = 5000000): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => (string) $amount])
            ->assertSessionHasNoErrors();
    }

    private function serahkanModal(float $amount): CashTransfer
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $this->teller->id,
                'amount' => (string) $amount,
            ])
            ->assertSessionHasNoErrors();

        return CashTransfer::where('teller_id', $this->teller->id)
            ->where('type', 'opening')
            ->latest('id')
            ->firstOrFail();
    }

    private function konfirmasiModal(CashTransfer $transfer): void
    {
        $this->actingAs($this->teller)
            ->post(route('teller.kas.konfirmasi-modal', $transfer))
            ->assertSessionHasNoErrors();
    }

    /**
     * Teller memakai kas laci untuk penarikan nasabah (kas laci berkurang).
     */
    private function tarikTunaiDariLaci(float $amount): void
    {
        $nasabah = User::create([
            'name' => 'Irfan Cahyo',
            'username' => 'irfan',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $account = Account::create([
            'user_id' => $nasabah->id,
            'account_number' => 'TKJ-2026-01-02',
            'pin' => bcrypt('123456'),
            'balance' => 100000,
            'status' => 'active',
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-UJI-0001',
            'type' => 'withdrawal',
            'amount' => $amount,
            'status' => 'success',
            'description' => 'Tarik tunai nasabah',
        ]);
    }

    public function test_alur_lengkap_tambah_modal_sampai_laporan_harian(): void
    {
        $cash = app(CashDrawerService::class);

        $this->setVault(5000000);

        // 1. Supervisor menyerahkan modal awal Rp 100.000 dan Teller mengonfirmasi.
        $modalAwal = $this->serahkanModal(100000);
        $this->konfirmasiModal($modalAwal);

        $this->assertSame(100000.0, $cash->tellerBalance($this->teller->id));

        // 2. Modal habis dipakai tarik tunai nasabah → kas laci Rp 0.
        $this->tarikTunaiDariLaci(100000);

        $this->assertSame(0.0, $cash->tellerBalance($this->teller->id));

        // Dashboard Teller menawarkan pengajuan tambah modal (tanpa isi nominal).
        $this->actingAs($this->teller)
            ->get(route('teller.dashboard'))
            ->assertOk()
            ->assertSee('Kas laci sudah habis')
            ->assertSee('Ajukan Tambah Modal')
            ->assertSee('Teller tidak menentukan nominal');

        // 3. Teller mengajukan tambah modal tanpa nominal.
        $this->actingAs($this->teller)
            ->post(route('teller.kas.ajukan-modal'), ['note' => 'Kas laci habis untuk penarikan'])
            ->assertSessionHasNoErrors();

        $permintaan = CashRequest::firstOrFail();

        $this->assertSame('pending', $permintaan->status);
        $this->assertSame($this->teller->id, $permintaan->teller_id);
        $this->assertSame('Kas laci habis untuk penarikan', $permintaan->note);

        // Teller melihat status menunggu Supervisor.
        $this->actingAs($this->teller)
            ->get(route('teller.dashboard'))
            ->assertOk()
            ->assertSee('masih menunggu diproses')
            ->assertDontSee('Ajukan Tambah Modal');

        // 4. Supervisor mendapat pesan & melihat permintaan pada halaman rekonsiliasi.
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSee('permintaan tambah modal dari Teller');

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertSee('Permintaan Tambah Modal dari Teller')
            ->assertSee('Kas laci habis untuk penarikan')
            ->assertSee('Budi Teller');

        // 5. Supervisor menentukan nominal tambahan Rp 100.000.
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.penuhi-modal', $permintaan), ['amount' => '100000'])
            ->assertSessionHasNoErrors();

        $this->assertSame('fulfilled', $permintaan->fresh()->status);
        $this->assertNotNull($permintaan->fresh()->cash_transfer_id);

        // 6. Teller mengonfirmasi modal tambahan → kas laci Rp 100.000 lagi.
        $modalTambahan = CashTransfer::where('teller_id', $this->teller->id)
            ->where('type', 'opening')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(100000.0, (float) $modalTambahan->amount);
        $this->assertSame('pending', $modalTambahan->status);

        $this->konfirmasiModal($modalTambahan);

        $this->assertSame(100000.0, $cash->tellerBalance($this->teller->id));

        // 7. Laporan harian memakai total modal: 100.000 + 100.000 = 200.000.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.draft'))
            ->assertSessionHasNoErrors();

        $report = DailyReport::where('teller_id', $this->teller->id)->firstOrFail();

        $this->assertSame(200000.0, (float) $report->saldo_awal);
    }

    public function test_modal_tambahan_ditolak_saat_modal_sebelumnya_belum_dikonfirmasi(): void
    {
        $this->setVault(5000000);

        // Modal pertama masih pending (belum dikonfirmasi Teller).
        $this->serahkanModal(100000);

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $this->teller->id,
                'amount' => '50000',
            ])
            ->assertSessionHasErrors('kas');

        $this->assertStringContainsString(
            'Masih ada modal yang belum dikonfirmasi Teller',
            (string) session('errors')->first('kas')
        );

        $this->assertSame(1, CashTransfer::where('type', 'opening')->count());
    }

    public function test_teller_tidak_bisa_mengajukan_tambah_modal_dua_kali_dalam_sehari(): void
    {
        $this->setVault(5000000);

        $this->actingAs($this->teller)
            ->post(route('teller.kas.ajukan-modal'), ['note' => 'Kas habis'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teller)
            ->post(route('teller.kas.ajukan-modal'), ['note' => 'Kas habis lagi'])
            ->assertSessionHasErrors('kas');

        $this->assertSame(1, CashRequest::where('status', 'pending')->count());
    }

    public function test_penyerahan_modal_langsung_supervisor_menutup_permintaan_teller(): void
    {
        $this->setVault(5000000);

        $this->actingAs($this->teller)
            ->post(route('teller.kas.ajukan-modal'))
            ->assertSessionHasNoErrors();

        $permintaan = CashRequest::firstOrFail();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $this->teller->id,
                'amount' => '150000',
                'description' => 'Tambahan modal',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('fulfilled', $permintaan->fresh()->status);
        $this->assertNotNull($permintaan->fresh()->cash_transfer_id);
    }

    public function test_permintaan_tambah_modal_tidak_bisa_dipenuhi_dua_kali(): void
    {
        $this->setVault(5000000);

        $this->actingAs($this->teller)
            ->post(route('teller.kas.ajukan-modal'))
            ->assertSessionHasNoErrors();

        $permintaan = CashRequest::firstOrFail();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.penuhi-modal', $permintaan), ['amount' => '100000'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.penuhi-modal', $permintaan), ['amount' => '100000'])
            ->assertSessionHasErrors('kas');

        $this->assertSame(1, CashTransfer::where('type', 'opening')->count());
    }
}
