<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\DailyReport;
use App\Models\ReportNotification;
use App\Models\Transaction;
use App\Models\TransactionCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCorrectionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $teller;

    private User $supervisor;

    private Account $account;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teller = User::create([
            'name' => 'Teller Koreksi',
            'username' => 'teller-koreksi',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $this->supervisor = User::create([
            'name' => 'Supervisor Koreksi',
            'username' => 'supervisor-koreksi',
            'password' => 'password',
            'role' => 'supervisor',
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Nasabah Koreksi',
            'username' => 'nasabah-koreksi',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->account = Account::create([
            'user_id' => $customer->id,
            'account_number' => 'KOR-0001',
            'pin' => bcrypt('123456'),
            'balance' => 50000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        $tanggalLama = today()->subDay();
        $this->transaction = Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-KOR-0001',
            'type' => 'deposit',
            'amount' => 10000,
            'status' => 'success',
            'created_at' => $tanggalLama,
            'updated_at' => $tanggalLama,
        ]);
    }

    public function test_teller_dapat_membuka_riwayat_lama_dan_mengajukan_koreksi_sebelum_laporan_dikirim(): void
    {
        $this->actingAs($this->teller)
            ->get(route('teller.koreksi', ['tanggal' => today()->subDay()->toDateString()]))
            ->assertOk()
            ->assertSee('TRX-KOR-0001')
            ->assertSee('Pilih transaksi pada tanggal riwayat');

        $this->actingAs($this->teller)
            ->post(route('teller.koreksi.store'), [
                'transaction_id' => $this->transaction->id,
                'jenis_koreksi' => 'kurang',
                'amount' => 1000,
                'sandi_koreksi' => 'K01',
                'reason' => 'Nominal transaksi terinput berlebih.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', TransactionCorrection::firstOrFail()->status);
    }

    public function test_teller_tidak_dapat_mengajukan_koreksi_setelah_laporan_pernah_dikirim(): void
    {
        DailyReport::create([
            'teller_id' => $this->teller->id,
            'report_date' => today()->subDay()->toDateString(),
            'status' => 'rejected',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->teller)
            ->post(route('teller.koreksi.store'), [
                'transaction_id' => $this->transaction->id,
                'jenis_koreksi' => 'kurang',
                'amount' => 1000,
                'sandi_koreksi' => 'K01',
                'reason' => 'Nominal transaksi terinput berlebih.',
            ])
            ->assertSessionHasErrors('transaction_id');

        $this->assertSame(0, TransactionCorrection::count());
    }

    public function test_kode_kelebihan_input_harus_mengurangi_saldo(): void
    {
        $this->actingAs($this->teller)
            ->post(route('teller.koreksi.store'), [
                'transaction_id' => $this->transaction->id,
                'jenis_koreksi' => 'tambah',
                'amount' => 1000,
                'sandi_koreksi' => 'K01',
                'reason' => 'Nominal transaksi terinput berlebih.',
            ])
            ->assertSessionHasErrors('jenis_koreksi');

        $this->assertSame(0, TransactionCorrection::count());
    }

    public function test_penolakan_supervisor_mengirim_notifikasi_dengan_alasan_ke_teller(): void
    {
        $correction = TransactionCorrection::create([
            'transaction_id' => $this->transaction->id,
            'teller_id' => $this->teller->id,
            'type' => 'withdrawal_correction',
            'corrected_amount' => 1000,
            'correction_code' => 'K01',
            'reason' => 'Nominal transaksi terinput berlebih.',
            'status' => 'pending',
        ]);

        $alasan = 'Bukti transaksi tidak sesuai.';

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.koreksi.reject', $correction->id), ['reason' => $alasan])
            ->assertSessionHasNoErrors();

        $this->assertSame($alasan, $correction->fresh()->rejected_reason);

        $notification = ReportNotification::where('user_id', $this->teller->id)->firstOrFail();
        $this->assertSame('correction_rejected', $notification->type);
        $this->assertStringContainsString($alasan, $notification->message);

        $this->actingAs($this->teller)
            ->get(route('teller.notifikasi'))
            ->assertOk()
            ->assertSee('Pengajuan koreksi ditolak')
            ->assertSee($alasan);
    }
}