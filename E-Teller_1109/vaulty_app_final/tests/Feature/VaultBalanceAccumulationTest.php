<?php

namespace Tests\Feature;

use App\Models\CashTransfer;
use App\Models\DailyReport;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CashDrawerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saldo brankas Supervisor harus berjalan (kumulatif):
 * saldo awal dipakai terus-menerus, lalu bertambah/berkurang
 * mengikuti pergerakan kas dan tidak direset setiap hari.
 */
class VaultBalanceAccumulationTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private CashDrawerService $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::create([
            'name' => 'Bapak Supervisor',
            'username' => 'spv',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        $this->cash = app(CashDrawerService::class);
    }

    private function teller(string $username, string $name): User
    {
        return User::create([
            'name' => $name,
            'username' => $username,
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    private function transfer(
        User $teller,
        string $type,
        float $amount,
        string $status,
        string $date
    ): CashTransfer {
        return CashTransfer::create([
            'teller_id' => $teller->id,
            'type' => $type,
            'transfer_date' => $date,
            'amount' => $amount,
            'status' => $status,
            'created_by' => $this->supervisor->id,
            'confirmed_by' => $status === 'confirmed' ? $teller->id : null,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    public function test_saldo_awal_disimpan_sekali_untuk_semua_hari(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '1000000'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_settings', [
            'key' => 'vault_initial_balance',
            'value' => '1000000',
        ]);

        // Tidak ada lagi pengaturan saldo awal per tanggal.
        $perTanggal = SystemSetting::pluck('key')
            ->filter(fn ($key) => str_starts_with((string) $key, 'vault_initial_balance_'));

        $this->assertCount(0, $perTanggal);
    }

    public function test_saldo_brankas_saat_ini_kumulatif_dari_semua_hari(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '1000000'])
            ->assertSessionHasNoErrors();

        $tellerA = $this->teller('teller-a', 'Teller A');
        $tellerB = $this->teller('teller-b', 'Teller B');

        // Kemarin: modal keluar Rp 200.000, kas kembali Rp 50.000.
        $this->transfer($tellerA, 'opening', 200000, 'confirmed', today()->subDay()->toDateString());
        $this->transfer($tellerA, 'closing', 50000, 'confirmed', today()->subDay()->toDateString());

        // Hari ini: modal keluar Rp 100.000 (menunggu konfirmasi Teller).
        $this->transfer($tellerB, 'opening', 100000, 'pending', today()->toDateString());

        // 1.000.000 - 200.000 + 50.000 - 100.000 = 750.000
        $this->assertSame(750000.0, $this->cash->supervisorBalance());

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertSee('Saldo Brankas Saat Ini')
            ->assertSee('Rp 1.000.000')
            ->assertSee('Rp 750.000');
    }

    public function test_saldo_awal_tidak_reset_besok_hari(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '1000000'])
            ->assertSessionHasNoErrors();

        $this->travel(1)->days();

        $this->assertSame(1000000.0, $this->cash->vaultInitialBalance());
        $this->assertSame(1000000.0, $this->cash->supervisorBalance());

        // Halaman tetap menampilkan saldo awal yang sama (bukan nol).
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertSee('Rp 1.000.000');
    }

    public function test_penyerahan_modal_memakai_saldo_brankas_kumulatif(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '500000'])
            ->assertSessionHasNoErrors();

        $tellerA = $this->teller('teller-a', 'Teller A');
        $tellerB = $this->teller('teller-b', 'Teller B');

        // Kemarin sudah menyerahkan Rp 400.000 → saldo brankas Rp 100.000.
        $this->transfer($tellerA, 'opening', 400000, 'confirmed', today()->subDay()->toDateString());

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $tellerB->id,
                'amount' => '150000',
            ])
            ->assertSessionHasErrors('kas');

        $pesan = (string) session('errors')->first('kas');

        $this->assertStringContainsString('Saldo brankas tidak mencukupi', $pesan);
        $this->assertStringContainsString('Rp 100.000', $pesan);

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $tellerB->id,
                'amount' => '100000',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cash_transfers', [
            'teller_id' => $tellerB->id,
            'type' => 'opening',
            'status' => 'pending',
            'amount' => 100000,
        ]);

        // Modal yang baru diserahkan langsung mengurangi saldo brankas.
        $this->assertSame(0.0, $this->cash->supervisorBalance());
    }

    public function test_saldo_brankas_saat_ini_tidak_terpengaruh_filter_tanggal(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '1000000'])
            ->assertSessionHasNoErrors();

        $tellerA = $this->teller('teller-a', 'Teller A');
        $tellerB = $this->teller('teller-b', 'Teller B');

        $this->transfer($tellerA, 'opening', 200000, 'confirmed', today()->subDay()->toDateString());
        $this->transfer($tellerB, 'opening', 100000, 'pending', today()->toDateString());

        // Filter tanggal hanya memengaruhi tabel laporan Teller; saldo brankas
        // yang ditampilkan tetap saldo berjalan saat ini (1.000.000 - 300.000).
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi', [
                'tanggal' => today()->subDay()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Rp 700.000');
    }

    public function test_saldo_awal_data_lama_per_tanggal_tetap_terbaca(): void
    {
        SystemSetting::create([
            'key' => 'vault_initial_balance_2026-11-05',
            'value' => '900000',
        ]);

        $this->assertTrue($this->cash->hasVaultInitialBalance());
        $this->assertSame(900000.0, $this->cash->vaultInitialBalance());

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertSee('Rp 900.000');
    }

    public function test_saldo_awal_dapat_diperbarui_walau_ada_modal_keluar(): void
    {
        $teller = $this->teller('teller-a', 'Teller A');

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '1000000'])
            ->assertSessionHasNoErrors();

        $this->transfer($teller, 'opening', 100000, 'confirmed', today()->toDateString());

        // Perubahan saldo awal tidak lagi diblok oleh pergerakan kas.
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '5000000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(5000000.0, $this->cash->vaultInitialBalance());

        /*
         * Saldo brankas saat ini langsung sama dengan saldo awal yang baru
         * disimpan. Modal yang sudah keluar sebelum penyimpanan dianggap sudah
         * tercermin pada angka yang diisi Supervisor.
         */
        $this->assertSame(5000000.0, $this->cash->supervisorBalance());

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertSee('Rp 5.000.000')
            ->assertDontSee('Rp 4.900.000');

        // Baru berkurang setelah modal berikutnya benar-benar diserahkan.
        $this->transfer($teller, 'opening', 100000, 'pending', today()->toDateString());

        $this->assertSame(4900000.0, $this->cash->supervisorBalance());
    }

    public function test_saldo_brankas_tidak_ditampilkan_minus(): void
    {
        // Kasus data lama: saldo awal tersimpan 0, tetapi sudah ada modal keluar.
        SystemSetting::create(['key' => 'vault_initial_balance', 'value' => '0']);

        $teller = $this->teller('teller-a', 'Teller A');
        $this->transfer($teller, 'opening', 100000, 'confirmed', today()->toDateString());

        $this->assertSame(0.0, $this->cash->vaultInitialBalance());
        $this->assertSame(0.0, $this->cash->supervisorBalance());

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertDontSee('Rp -100.000');
    }

    public function test_peringatan_muncul_bila_modal_keluar_melebihi_saldo_awal(): void
    {
        $teller = $this->teller('teller-a', 'Teller A');

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '1000000'])
            ->assertSessionHasNoErrors();

        // Modal keluar setelah saldo awal disimpan melebihi saldo brankas.
        $this->transfer($teller, 'opening', 1500000, 'confirmed', today()->toDateString());

        $this->assertSame(-500000.0, $this->cash->supervisorBalance());

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.rekonsiliasi'))
            ->assertOk()
            ->assertDontSee('Rp -500.000')
            ->assertSee('lebih kecil dari modal yang sudah diserahkan');
    }

    public function test_kas_laporan_harian_yang_disetujui_menambah_saldo_brankas(): void
    {
        $teller = $this->teller('teller-a', 'Teller A');

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '5000000'])
            ->assertSessionHasNoErrors();

        // Modal Rp 100.000 diserahkan dan dikonfirmasi Teller.
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $teller->id,
                'amount' => '100000',
            ])
            ->assertSessionHasNoErrors();

        $modal = CashTransfer::where('teller_id', $teller->id)
            ->where('type', 'opening')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($teller)
            ->post(route('teller.kas.konfirmasi-modal', $modal))
            ->assertSessionHasNoErrors();

        // Modal keluar → saldo brankas berkurang.
        $this->assertSame(4900000.0, $this->cash->supervisorBalance());

        // Teller membuat & mengirim laporan harian (uang fisik masih utuh Rp 100.000).
        $this->actingAs($teller)
            ->post(route('teller.laporan-harian.draft'))
            ->assertSessionHasNoErrors();

        $this->actingAs($teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'pecahan_100k' => 1,
                'pecahan_50k' => 0,
                'pecahan_20k' => 0,
                'pecahan_5k' => 0,
            ])
            ->assertSessionHasNoErrors();

        $report = DailyReport::where('teller_id', $teller->id)->firstOrFail();
        $this->assertSame('submitted', $report->status);

        // Supervisor menyetujui laporan lalu mencatat kas masuk ke brankas.
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.approve', $report))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.transfer', $report))
            ->assertSessionHasNoErrors();

        // Kas kembali ke brankas → saldo brankas kembali Rp 5.000.000.
        $this->assertSame(5000000.0, $this->cash->supervisorBalance());

        $this->assertDatabaseHas('cash_transfers', [
            'teller_id' => $teller->id,
            'daily_report_id' => $report->id,
            'type' => 'closing',
            'status' => 'confirmed',
            'amount' => 100000,
        ]);
    }
}
