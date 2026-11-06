<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashTransfer;
use App\Models\DailyReport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji cetakan rekap laporan harian Teller (penutupan kas):
 * rekapitulasi, terbilang, rincian lembaran, daftar transaksi,
 * serta hak akses pencetakan.
 */
class DailyReportPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $teller;

    private User $tellerLain;

    private Account $account;

    private DailyReport $report;

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
            'name' => 'Teller Loket 1',
            'username' => 'teller',
            'password' => 'password',
            'role' => 'teller',
        ]);

        $this->tellerLain = User::create([
            'name' => 'Teller Loket 2',
            'username' => 'teller2',
            'password' => 'password',
            'role' => 'teller',
        ]);

        $nasabah = User::create([
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $this->account = Account::create([
            'user_id' => $nasabah->id,
            'account_number' => 'RPL-2026-001',
            'pin' => bcrypt('123456'),
            'balance' => 245000,
        ]);

        CashTransfer::create([
            'teller_id' => $this->teller->id,
            'type' => 'opening',
            'transfer_date' => today()->toDateString(),
            'amount' => 150000,
            'status' => 'confirmed',
            'created_by' => $this->supervisor->id,
            'confirmed_by' => $this->teller->id,
            'confirmed_at' => now(),
        ]);

        $this->report = DailyReport::create([
            'teller_id' => $this->teller->id,
            'report_date' => today()->toDateString(),
            'saldo_awal' => 150000,
            'pecahan_100k' => 2,
            'pecahan_50k' => 0,
            'pecahan_20k' => 2,
            'pecahan_5k' => 1,
            'pecahan_awal_100k' => 1,
            'pecahan_awal_50k' => 1,
            'deposit_total' => 125000,
            'withdrawal_total' => 30000,
            'transaction_count' => 2,
            'expected_cash' => 95000,
            'status' => 'approved',
            'approved_by' => $this->supervisor->id,
            'approved_at' => now(),
            'submitted_at' => now(),
            'transfer_status' => 'pending',
        ]);

        Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-TEST-001',
            'type' => 'deposit',
            'amount' => 125000,
            'status' => 'success',
        ]);

        Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-TEST-002',
            'type' => 'withdrawal',
            'amount' => 30000,
            'status' => 'success',
        ]);
    }

    public function test_cetakan_rekap_laporan_harian_sesuai_format(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan.cetak-laporan', $this->report->id))
            ->assertOk()
            // Kepala laporan
            ->assertSee('Laporan Harian Teller (Penutupan Kas)')
            ->assertSee('Teller Loket 1')
            ->assertSee('Disetujui')
            // Rekapitulasi
            ->assertSee('Rekapitulasi')
            ->assertSee('Rp150.000')
            ->assertSee('Total setoran (1 transaksi)')
            ->assertSee('Rp125.000')
            ->assertSee('Rp30.000')
            ->assertSee('Rp245.000')
            ->assertSee('Uang fisik di laci')
            // Terbilang
            ->assertSee('dua ratus empat puluh lima ribu rupiah')
            // Rincian lembaran saldo awal & uang fisik
            ->assertSee('Rincian lembaran: Saldo awal')
            ->assertSee('Rincian lembaran: Uang fisik')
            ->assertSee('Rp5.000')
            // Daftar transaksi
            ->assertSee('RPL-2026-001')
            ->assertSee('Budi Santoso')
            ->assertSee('Setor tunai')
            ->assertSee('Tarik tunai')
            // Catatan verifikasi & tanda tangan
            ->assertSee('Diverifikasi oleh Bapak Supervisor')
            ->assertSee('Catatan: Sesuai');
    }

    public function test_teller_dapat_mencetak_laporan_sendiri(): void
    {
        $this->actingAs($this->teller)
            ->get(route('teller.laporan.cetak', $this->report->id))
            ->assertOk()
            ->assertSee('dua ratus empat puluh lima ribu rupiah');
    }

    public function test_teller_tidak_dapat_mencetak_laporan_teller_lain(): void
    {
        $this->actingAs($this->tellerLain)
            ->get(route('teller.laporan.cetak', $this->report->id))
            ->assertForbidden();
    }

    public function test_rincian_lembaran_saldo_awal_harus_sama_dengan_modal(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // 1 lembar Rp100.000 = Rp100.000, tidak sama dengan modal Rp150.000.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'tanggal' => today()->toDateString(),
                'pecahan_100k' => 2,
                'pecahan_50k' => 0,
                'pecahan_20k' => 2,
                'pecahan_5k' => 1,
                'pecahan_awal_100k' => 1,
                'pecahan_awal_50k' => 0,
            ])
            ->assertSessionHasErrors('report');

        $this->assertSame('draft', $this->report->fresh()->status);
    }

    public function test_kirim_laporan_menyimpan_rincian_pecahan_lima_ribu(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'tanggal' => today()->toDateString(),
                'pecahan_100k' => 2,
                'pecahan_50k' => 0,
                'pecahan_20k' => 2,
                'pecahan_5k' => 1,
                'pecahan_awal_100k' => 1,
                'pecahan_awal_50k' => 1,
            ])
            ->assertSessionHasNoErrors();

        $report = $this->report->fresh();

        $this->assertSame('submitted', $report->status);
        $this->assertSame(1, (int) $report->pecahan_5k);
        $this->assertSame(1, (int) $report->pecahan_awal_100k);
        $this->assertSame(245000.0, $report->totalFisik());
        $this->assertSame(150000.0, $report->totalFisikAwal());
        $this->assertSame(0.0, $report->selisihKas());
    }
}
