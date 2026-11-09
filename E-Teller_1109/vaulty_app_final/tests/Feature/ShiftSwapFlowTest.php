<?php

namespace Tests\Feature;

use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\ReportNotification;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\ShiftSwapService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian alur TUKAR SHIFT + notifikasi Supervisor/Admin + kunci transaksi.
 */
class ShiftSwapFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $admin;

    private User $tellerA;   // yang minta izin

    private User $tellerB;   // pengganti

    private DutyShift $shiftPagi;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 9 November 2026 pukul 08:00 (di dalam jendela shift 06:45-09:30).
        Carbon::setTestNow(Carbon::parse('2026-11-09 08:00:00'));

        $this->supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'SPV Swap', 'status' => 'active']);
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Swap', 'status' => 'active']);
        $this->tellerA = User::factory()->create(['role' => 'teller', 'name' => 'Teller A', 'kelas' => 'XI AKL 1', 'status' => 'active']);
        $this->tellerB = User::factory()->create(['role' => 'teller', 'name' => 'Teller B', 'kelas' => 'X TKJ 2', 'status' => 'active']);

        $this->shiftPagi = DutyShift::create([
            'name' => 'Shift Pagi', 'start_time' => '07:00:00', 'end_time' => '09:30:00', 'is_active' => true,
        ]);

        // Hanya Teller A yang dijadwalkan Senin (Teller B tidak jadwal Senin).
        DutyWeeklySchedule::create([
            'day_of_week' => 1,
            'duty_shift_id' => $this->shiftPagi->id,
            'user_id' => $this->tellerA->id,
            'created_by' => $this->supervisor->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Tanggal pertama yang boleh dipilih (H+2, hari kerja). */
    private function tanggalBolehDipilih(): string
    {
        return ShiftSwapRequest::tanggalYangBolehDipilih()[0]->toDateString();
    }

    /** Buat pengajuan tukar shift untuk HARI INI (untuk uji kunci transaksi). */
    private function swapHariIni(string $status = 'pending'): ShiftSwapRequest
    {
        return ShiftSwapRequest::create([
            'requester_id' => $this->tellerA->id,
            'substitute_id' => $this->tellerB->id,
            'duty_date' => today()->toDateString(),
            'duty_shift_id' => $this->shiftPagi->id,
            'reason' => 'Ada rapat hari ini',
            'status' => $status,
            'approved_by' => $status === 'approved' ? $this->supervisor->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);
    }

    public function test_teller_mengajukan_tukar_shift_dan_supervisor_admin_mendapat_notifikasi(): void
    {
        $tanggal = $this->tanggalBolehDipilih();

        // Pastikan Teller A memang terjadwal pada hari kerja tanggal tersebut.
        DutyWeeklySchedule::firstOrCreate(
            [
                'day_of_week' => Carbon::parse($tanggal)->dayOfWeekIso,
                'duty_shift_id' => $this->shiftPagi->id,
                'user_id' => $this->tellerA->id,
            ],
            ['created_by' => $this->supervisor->id]
        );

        $this->actingAs($this->tellerA)
            ->post(route('teller.tukar-shift.store'), [
                'tanggal' => $tanggal,
                'duty_shift_id' => $this->shiftPagi->id,
                'substitute_id' => $this->tellerB->id,
                'reason' => 'Ada rapat di sekolah',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shift_swap_requests', [
            'requester_id' => $this->tellerA->id,
            'substitute_id' => $this->tellerB->id,
            'status' => 'pending',
        ]);

        // Notifikasi darurat ke Supervisor & Admin.
        $this->assertSame(1, ReportNotification::where('user_id', $this->supervisor->id)->where('type', 'shift_swap_request')->count());
        $this->assertSame(1, ReportNotification::where('user_id', $this->admin->id)->where('type', 'shift_swap_request')->count());
    }

    public function test_tanggal_kurang_dari_dua_hari_ditolak(): void
    {
        $this->actingAs($this->tellerA)
            ->post(route('teller.tukar-shift.store'), [
                'tanggal' => today()->addDay()->toDateString(), // besok = terlalu cepat
                'duty_shift_id' => $this->shiftPagi->id,
                'substitute_id' => $this->tellerB->id,
                'reason' => 'Ada rapat di sekolah',
            ])
            ->assertSessionHasErrors('tukar_shift');

        $this->assertDatabaseCount('shift_swap_requests', 0);
    }

    public function test_hanya_boleh_satu_kali_tukar_shift_dalam_tujuh_hari(): void
    {
        ShiftSwapRequest::create([
            'requester_id' => $this->tellerA->id,
            'substitute_id' => $this->tellerB->id,
            'duty_date' => $this->tanggalBolehDipilih(),
            'duty_shift_id' => $this->shiftPagi->id,
            'reason' => 'Rapat pertama',
            'status' => 'pending',
        ]);

        $this->actingAs($this->tellerA)
            ->post(route('teller.tukar-shift.store'), [
                'tanggal' => $this->tanggalBolehDipilih(),
                'duty_shift_id' => $this->shiftPagi->id,
                'substitute_id' => $this->tellerB->id,
                'reason' => 'Rapat kedua',
            ])
            ->assertSessionHasErrors('tukar_shift');

        $this->assertSame(1, ShiftSwapRequest::count());
    }

    public function test_setelah_disetujui_teller_b_menggantikan_dan_teller_a_terkunci(): void
    {
        $swap = $this->swapHariIni();

        $service = app(ShiftSwapService::class);

        // Sebelum disetujui: A bertugas, B tidak.
        $this->assertTrue($service->bertugasHariIni($this->tellerA));
        $this->assertFalse($service->bertugasHariIni($this->tellerB));

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.tukar-shift.setujui', $swap->id))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $swap->refresh();
        $this->assertSame('approved', $swap->status);
        $this->assertSame($this->supervisor->id, $swap->approved_by);

        // Kunci berpindah: A tidak bertugas, B bertugas.
        $this->assertFalse($service->bertugasHariIni($this->tellerA));
        $this->assertTrue($service->bertugasHariIni($this->tellerB));

        // Notifikasi untuk A dan B.
        $this->assertSame(1, ReportNotification::where('user_id', $this->tellerA->id)->where('type', 'shift_swap_approved')->count());
        $this->assertSame(1, ReportNotification::where('user_id', $this->tellerB->id)->where('type', 'shift_swap_approved')->count());
    }

    public function test_teller_pengganti_bisa_absen_dan_teller_asli_ditolak(): void
    {
        $this->swapHariIni('approved');

        // Teller B (pengganti) bisa absen masuk.
        $this->actingAs($this->tellerB)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // Teller A tidak bisa absen (shift sudah ditukar).
        $this->actingAs($this->tellerA)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
            ->assertSessionHasErrors('absensi');
    }

    public function test_penjagaan_transaksi_hanya_untuk_teller_yang_bertugas(): void
    {
        $this->swapHariIni('approved');

        // Teller A (sudah digantikan) diblokir middleware "teller.bertugas".
        $this->actingAs($this->tellerA)
            ->post(route('teller.laporan-harian.draft'))
            ->assertSessionHasErrors('jadwal');

        // Teller B (pengganti) tidak diblokir.
        $this->actingAs($this->tellerB)
            ->post(route('teller.laporan-harian.draft'))
            ->assertSessionDoesntHaveErrors('jadwal');
    }

    public function test_supervisor_dan_admin_bisa_melihat_halaman_notifikasi(): void
    {
        ReportNotification::create([
            'user_id' => $this->supervisor->id,
            'title' => 'Pengajuan tukar shift',
            'message' => 'Teller A mengajukan tukar shift.',
            'type' => 'shift_swap_request',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.notifikasi'))
            ->assertOk()
            ->assertSee('Teller A mengajukan tukar shift');

        $this->actingAs($this->admin)
            ->get(route('admin.notifikasi'))
            ->assertOk()
            ->assertSee('Kotak Masuk');

        $this->actingAs($this->tellerA)
            ->get(route('admin.notifikasi'))
            ->assertForbidden();
    }

    public function test_supervisor_bisa_menolak_pengajuan(): void
    {
        $swap = ShiftSwapRequest::create([
            'requester_id' => $this->tellerA->id,
            'substitute_id' => $this->tellerB->id,
            'duty_date' => $this->tanggalBolehDipilih(),
            'duty_shift_id' => $this->shiftPagi->id,
            'reason' => 'Ada rapat',
            'status' => 'pending',
        ]);

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.tukar-shift.tolak', $swap->id), ['rejected_reason' => 'Jadwal pengganti bentrok'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $swap->refresh();
        $this->assertSame('rejected', $swap->status);
        $this->assertSame('Jadwal pengganti bentrok', $swap->rejected_reason);
        $this->assertSame(1, ReportNotification::where('user_id', $this->tellerA->id)->where('type', 'shift_swap_rejected')->count());
    }
}
