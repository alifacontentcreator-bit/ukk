<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario daily report rejection test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\CashTransfer;
use App\Models\DailyReport;
use App\Models\ReportNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportRejectionTest extends TestCase
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

        // Modal yang sudah dikonfirmasi Teller hari ini.
        CashTransfer::create([
            'teller_id' => $this->teller->id,
            'type' => 'opening',
            'transfer_date' => today()->toDateString(),
            'amount' => 1000000,
            'status' => 'confirmed',
            'created_by' => $this->supervisor->id,
            'confirmed_by' => $this->teller->id,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Laporan yang dikirim Teller dengan uang fisik lebih besar
     * Rp 100.000 dari catatan sistem (kas tidak klop).
     */
    private function laporanBerselisih(): DailyReport
    {
        return DailyReport::create([
            'teller_id' => $this->teller->id,
            'report_date' => today()->toDateString(),
            'saldo_awal' => 1000000,
            'pecahan_100k' => 11,
            'pecahan_50k' => 0,
            'pecahan_20k' => 0,
            'deposit_total' => 0,
            'withdrawal_total' => 0,
            'transaction_count' => 0,
            'expected_cash' => 0,
            'status' => 'submitted',
            'submitted_at' => now(),
            'transfer_status' => 'pending',
        ]);
    }

    public function test_supervisor_melihat_tombol_tolak_laporan(): void
    {
        $report = $this->laporanBerselisih();

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan'))
            ->assertOk()
            ->assertSee('Tolak laporan')
            ->assertSee(route('supervisor.laporan.reject', $report->id));
    }

    public function test_laporan_berselisih_tidak_dapat_disetujui(): void
    {
        $report = $this->laporanBerselisih();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.approve', $report->id))
            ->assertSessionHasErrors('report');

        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertSame(0, ReportNotification::count());
    }

    public function test_alasan_penolakan_wajib_diisi(): void
    {
        $report = $this->laporanBerselisih();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.reject', $report->id), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertSame(0, ReportNotification::count());
    }

    public function test_supervisor_menolak_laporan_dan_teller_menerima_notifikasi(): void
    {
        $report = $this->laporanBerselisih();

        $alasan = 'Kas fisik kurang Rp 100.000 dari catatan sistem.';

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.reject', $report->id), ['reason' => $alasan])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report->refresh();

        $this->assertSame('rejected', $report->status);
        $this->assertSame($alasan, $report->rejected_reason);
        $this->assertSame($this->supervisor->id, $report->rejected_by);
        $this->assertNotNull($report->rejected_at);

        $notification = ReportNotification::where('user_id', $this->teller->id)->firstOrFail();

        $this->assertSame('report_rejected', $notification->type);
        $this->assertSame($report->id, (int) $notification->daily_report_id);
        $this->assertNull($notification->read_at);

        // Teller melihat pemberitahuan dan tombol revisi pada laporan harian.
        $this->actingAs($this->teller)
            ->get(route('teller.laporan'))
            ->assertOk()
            ->assertSee('notifikasi baru dari Supervisor')
            ->assertSee('Laporan ditolak Supervisor')
            ->assertSee('Revisi Laporan Harian');

        // Halaman notifikasi memuat alasan penolakan Supervisor.
        $this->actingAs($this->teller)
            ->get(route('teller.notifikasi'))
            ->assertOk()
            ->assertSee('Laporan kas harian ditolak')
            ->assertSee($alasan)
            ->assertSee('Revisi Laporan Harian');

        // Notifikasi otomatis ditandai terbaca setelah halaman dibuka.
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_teller_dapat_merevisi_lalu_mengirim_ulang_laporan_yang_ditolak(): void
    {
        $report = $this->laporanBerselisih();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.reject', $report->id), [
                'reason' => 'Uang fisik belum sesuai catatan sistem.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.revisi', $report->id))
            ->assertRedirect(route('teller.laporan', ['tanggal' => today()->toDateString()]))
            ->assertSessionHasNoErrors();

        $report->refresh();

        $this->assertSame('draft', $report->status);
        $this->assertNull($report->rejected_reason);
        $this->assertNull($report->rejected_at);

        // Teller mengirim ulang dengan jumlah uang fisik yang sudah benar.
        $this->actingAs($this->teller)
            ->post(route('teller.laporan-harian.kirim'), [
                'tanggal' => today()->toDateString(),
                'pecahan_100k' => 10,
                'pecahan_50k' => 0,
                'pecahan_20k' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $report->fresh()->status);

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.laporan.approve', $report->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $report->fresh()->status);
        $this->assertSame($this->supervisor->id, $report->fresh()->approved_by);

        $this->assertSame(
            1,
            ReportNotification::where('user_id', $this->teller->id)
                ->where('type', 'report_approved')
                ->count()
        );
    }
}
