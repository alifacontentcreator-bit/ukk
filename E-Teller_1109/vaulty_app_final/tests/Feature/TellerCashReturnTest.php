<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario teller cash return test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\CashTransfer;
use App\Models\DailyReport;
use App\Models\User;
use App\Services\CashDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerCashReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::create([
            'name' => 'Supervisor',
            'username' => 'supervisor',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        $this->teller = User::create([
            'name' => 'Teller',
            'username' => 'teller',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    /**
     * Modal awal yang sudah diterima Teller hari ini.
     */
    private function modalDiterima(float $amount = 1000000): CashTransfer
    {
        return CashTransfer::create([
            'teller_id' => $this->teller->id,
            'type' => 'opening',
            'transfer_date' => today()->toDateString(),
            'amount' => $amount,
            'status' => 'confirmed',
            'created_by' => $this->supervisor->id,
            'confirmed_by' => $this->teller->id,
            'confirmed_at' => now(),
        ]);
    }

    public function test_dashboard_teller_menampilkan_info_kas_tersedia_di_laci(): void
    {
        $this->modalDiterima();

        $this->actingAs($this->teller)
            ->get(route('teller.dashboard'))
            ->assertOk()
            ->assertSee('Kas tersedia di laci')
            ->assertSee('Rp 1.000.000')
            // Form pengembalian kas sudah dihapus dari dashboard teller.
            ->assertDontSee('Jumlah kas dikembalikan')
            ->assertDontSee('Serahkan Kembali Kas');
    }

    public function test_teller_dapat_mengajukan_pengembalian_dan_supervisor_mengonfirmasinya(): void
    {
        $this->modalDiterima(1000000);

        $this->actingAs($this->teller)
            ->post(route('teller.kas.serahkan-kembali'), [
                'amount' => 400000,
                'description' => 'Setor kas berlebih ke brankas',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $transfer = CashTransfer::where('type', 'closing')->firstOrFail();

        $this->assertSame('pending', $transfer->status);
        $this->assertSame(400000.0, (float) $transfer->amount);

        // Kas laci belum berkurang sebelum dikonfirmasi Supervisor.
        $this->assertSame(1000000.0, app(CashDrawerService::class)
            ->tellerBalance($this->teller->id));

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertSee('Konfirmasi Pengembalian Kas');

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.konfirmasi-pengembalian', $transfer))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $transfer->fresh()->status);

        $cashService = app(CashDrawerService::class);

        $this->assertSame(600000.0, $cashService->tellerBalance($this->teller->id));

        // Brankas awal Rp 1.000.000 - modal Rp 1.000.000 + kas kembali Rp 400.000.
        $this->assertSame(400000.0, $cashService->supervisorBalance(1000000, today()->toDateString()));
    }

    public function test_pengembalian_kas_tidak_boleh_melebihi_kas_laci(): void
    {
        $this->modalDiterima(1000000);

        $this->actingAs($this->teller)
            ->post(route('teller.kas.serahkan-kembali'), [
                'amount' => 1500000,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('kas');

        $this->assertDatabaseCount('cash_transfers', 1);
    }

    public function test_pengembalian_kas_tidak_boleh_diajukan_dua_kali(): void
    {
        $this->modalDiterima(1000000);

        $this->actingAs($this->teller)
            ->post(route('teller.kas.serahkan-kembali'), ['amount' => 200000])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teller)
            ->post(route('teller.kas.serahkan-kembali'), ['amount' => 100000])
            ->assertSessionHasErrors('kas');

        $this->assertSame(1, CashTransfer::where('type', 'closing')->count());
    }

    public function test_supervisor_dapat_mencatat_kas_laporan_masuk_ke_brankas(): void
    {
        $report = DailyReport::create([
            'teller_id' => $this->teller->id,
            'report_date' => today()->toDateString(),
            'saldo_awal' => 1000000,
            'status' => 'approved',
            'approved_by' => $this->supervisor->id,
            'approved_at' => now(),
            'transfer_status' => 'pending',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan'))
            ->assertOk()
            ->assertSee('Pindahkan kas ke brankas');

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.transfer', $report))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $report->fresh()->transfer_status);
        $this->assertSame($this->supervisor->id, $report->fresh()->transferred_by);
    }
}
