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
            // Rincian lembaran: saldo awal & uang fisik (sesuai contoh laporan ke Supervisor)
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

    public function test_cetakan_draf_tidak_menampilkan_selisih_palsu(): void
    {
        // Draf belum menyimpan hasil hitung uang fisik (semua pecahan 0) dan
        // angka rekapnya masih kosong; saat dicetak angkanya disegarkan.
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
            'saldo_awal' => 150000,
            'pecahan_100k' => 0,
            'pecahan_20k' => 0,
            'pecahan_5k' => 0,
            'deposit_total' => 0,
            'withdrawal_total' => 0,
            'expected_cash' => 0,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan.cetak-laporan', $this->report->id))
            ->assertOk()
            // Uang fisik belum dihitung → tidak dicetak Rp0 dan tidak ada minus.
            ->assertSee('Belum dihitung')
            ->assertSee('dua ratus empat puluh lima ribu rupiah (kas akhir sistem)')
            // Angka rekap disegarkan dari transaksi hari itu.
            ->assertSee('Rp125.000')
            ->assertSee('Rp30.000')
            ->assertSee('Rp245.000');
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

    public function test_kirim_laporan_wajib_catatan_bila_selisih(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // Kas seharusnya Rp245.000 (modal 150.000 + setoran 125.000 - penarikan 30.000),
        // uang fisik hanya Rp200.000 → selisih -Rp45.000 (kas kurang).
        $fisik = [
            'tanggal' => today()->toDateString(),
            'pecahan_100k' => 2,
            'pecahan_50k' => 0,
            'pecahan_20k' => 0,
        ];

        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), $fisik)
            ->assertSessionHasErrors('report');

        $this->assertSame('draft', $this->report->fresh()->status);

        // Catatan teller wajib diisi sebagai penjelasan selisih.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), $fisik + [
                'catatan_selisih' => 'Uang fisik kurang, sedang diperiksa ulang.',
            ])
            ->assertSessionHasNoErrors();

        $report = $this->report->fresh();

        $this->assertSame('submitted', $report->status);
        $this->assertSame('Uang fisik kurang, sedang diperiksa ulang.', $report->catatan_selisih);
        $this->assertSame(-45000.0, $report->selisihKas());
    }

    public function test_selisih_nol_boleh_dikirim_tanpa_catatan(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // Uang fisik Rp245.000 = kas seharusnya Rp245.000 → selisih 0.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'tanggal' => today()->toDateString(),
                'pecahan_100k' => 2,
                'pecahan_50k' => 0,
                'pecahan_20k' => 2,
                'pecahan_5k' => 1,
            ])
            ->assertSessionHasNoErrors();

        $report = $this->report->fresh();

        $this->assertSame('submitted', $report->status);
        $this->assertSame(0.0, $report->selisihKas());
        $this->assertNull($report->catatan_selisih);
    }

    public function test_transaksi_penjelas_opsional_tersimpan_dan_tercetak(): void
    {
        // Setoran Rp125.000, penarikan 30.000 + 200.000 → kas seharusnya Rp45.000.
        $penarikan = Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-TEST-003',
            'type' => 'withdrawal',
            'amount' => 200000,
            'status' => 'success',
        ]);

        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // Uang fisik Rp45.000 = kas seharusnya → selisih 0.
        $fisik = [
            'tanggal' => today()->toDateString(),
            'pecahan_100k' => 0,
            'pecahan_50k' => 0,
            'pecahan_20k' => 2,
            'pecahan_10k' => 0,
            'pecahan_5k' => 1,
            // Opsional: transaksi hari itu tetap bisa dilampirkan.
            'transaksi_penjelas' => [$penarikan->id],
        ];

        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), $fisik)
            ->assertSessionHasNoErrors();

        $report = $this->report->fresh();

        $this->assertSame('submitted', $report->status);
        $this->assertSame(45000.0, $report->totalFisik());
        $this->assertSame(0.0, $report->selisihKas());
        $this->assertSame(
            [$penarikan->id],
            $report->transaksiPenjelas()->pluck('transactions.id')->all()
        );

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan.cetak-laporan', $report->id))
            ->assertOk()
            ->assertSee('Penjelasan selisih');
    }

    public function test_supervisor_melihat_perubahan_kas_bersih_dan_catatan_teller(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // Uang fisik Rp200.000 vs kas seharusnya Rp245.000 → selisih kurang.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'tanggal' => today()->toDateString(),
                'pecahan_100k' => 2,
                'pecahan_50k' => 0,
                'pecahan_20k' => 0,
                'catatan_selisih' => 'Dua lembar Rp20.000 belum ditemukan.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan'))
            ->assertOk()
            ->assertSee('Setoran masuk (1 transaksi)')
            ->assertSee('Penarikan keluar (1 transaksi)')
            ->assertSee('Perubahan kas bersih')
            ->assertSee('Kas bertambah')
            ->assertSee('Selisih Kurang')
            ->assertSee('Catatan teller:')
            ->assertSee('Dua lembar Rp20.000 belum ditemukan.');

        // Catatan teller juga ikut tercetak pada rekap.
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan.cetak-laporan', $this->report->id))
            ->assertOk()
            ->assertSee('Catatan teller: Dua lembar Rp20.000 belum ditemukan.');
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
            ])
            ->assertSessionHasNoErrors();

        $report = $this->report->fresh();

        $this->assertSame('submitted', $report->status);
        $this->assertSame(1, (int) $report->pecahan_5k);
        $this->assertSame(245000.0, $report->totalFisik());
        $this->assertSame(0.0, $report->selisihKas());
    }

    public function test_form_laporan_menampilkan_seluruh_pecahan(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->actingAs($this->teller)
            ->get(route('teller.laporan'))
            ->assertOk()
            ->assertSee('Rincian lembaran uang fisik kas akhir')
            // Rincian lembaran saldo awal sudah tidak ada.
            ->assertDontSee('Rincian lembaran saldo awal (opsional)')
            // Catatan sistem read-only + input uang fisik + catatan teller.
            ->assertSee('Catatan sistem (otomatis, tidak bisa diubah)')
            ->assertSee('Saldo awal laci (modal dari Supervisor)')
            ->assertSee('Kas seharusnya (saldo awal + setoran')
            ->assertSee('Catatan teller')
            ->assertSee('Transaksi hari ini (opsional)')
            ->assertSee('Pilih semua transaksi hari ini')
            ->assertSee('name="transaksi_penjelas[]"', false)
            // Uang fisik kas akhir: pecahan kecil juga harus bisa diisi.
            ->assertSee('name="pecahan_10k"', false)
            ->assertSee('name="pecahan_2k"', false)
            ->assertSee('name="pecahan_1k"', false)
            ->assertSee('name="pecahan_500"', false)
            ->assertSee('name="pecahan_100"', false)
            ->assertSee('name="pecahan_50"', false);
    }

    public function test_kirim_laporan_menyimpan_pecahan_kecil(): void
    {
        $this->report->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        // Uang fisik di laci dihitung lengkap sampai pecahan terkecil:
        // Rp10.000, Rp5.000, Rp2.000, Rp1.000, Rp500, Rp100, dan Rp50.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'tanggal' => today()->toDateString(),
                'pecahan_100k' => 0,
                'pecahan_50k' => 0,
                'pecahan_20k' => 0,
                'pecahan_10k' => 1,
                'pecahan_5k' => 2,
                'pecahan_2k' => 3,
                'pecahan_1k' => 4,
                'pecahan_500' => 1,
                'pecahan_100' => 2,
                'pecahan_50' => 1,
                // Uang fisik berbeda dari kas seharusnya → catatan wajib diisi.
                'catatan_selisih' => 'Uji input pecahan kecil.',
            ])
            ->assertSessionHasNoErrors();

        $report = $this->report->fresh();

        $this->assertSame('submitted', $report->status);
        $this->assertSame(1, (int) $report->pecahan_10k);
        $this->assertSame(3, (int) $report->pecahan_2k);
        $this->assertSame(4, (int) $report->pecahan_1k);
        $this->assertSame(1, (int) $report->pecahan_500);
        $this->assertSame(2, (int) $report->pecahan_100);
        $this->assertSame(1, (int) $report->pecahan_50);

        // 10.000 + (2 x 5.000) + (3 x 2.000) + (4 x 1.000) + 500 + 200 + 50
        $this->assertSame(30750.0, $report->totalFisik());
    }

    public function test_cetakan_menampilkan_pecahan_kecil_dengan_satuan_keping(): void
    {
        $this->report->update([
            'pecahan_100k' => 2,
            'pecahan_50k' => 0,
            'pecahan_20k' => 0,
            'pecahan_10k' => 4,
            'pecahan_5k' => 0,
            'pecahan_2k' => 2,
            'pecahan_1k' => 1,
            'pecahan_500' => 1,
            'pecahan_100' => 2,
            'pecahan_50' => 1,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan.cetak-laporan', $this->report->id))
            ->assertOk()
            ->assertSee('Rincian lembaran: Uang fisik')
            ->assertSee('Rp10.000')
            ->assertSee('Rp2.000')
            ->assertSee('Rp1.000')
            ->assertSee('Rp500')
            ->assertSee('Rp100')
            ->assertSee('4 lembar')
            ->assertSee('2 keping')
            ->assertSee('1 keping');
    }
}
