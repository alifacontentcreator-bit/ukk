<?php

namespace Tests\Feature;

use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jadwal jaga berbentuk template mingguan (Senin–Jumat):
 * Supervisor mengatur per hari, berlaku setiap minggu, dan dipakai
 * oleh absensi Teller.
 */
class DutyWeeklyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $teller;

    private User $tellerDua;

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
            'kelas' => 'X TKJ 1',
            'password' => 'password',
            'role' => 'teller',
        ]);

        $this->tellerDua = User::create([
            'name' => 'Sari Teller',
            'username' => 'sari',
            'kelas' => 'XI AKL 2',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    private function shift(string $nama = 'Shift 1', string $mulai = '07:00', string $selesai = '09:00'): DutyShift
    {
        return DutyShift::create([
            'name' => $nama,
            'start_time' => $mulai,
            'end_time' => $selesai,
            'is_active' => true,
        ]);
    }

    public function test_supervisor_menyimpan_jadwal_per_hari(): void
    {
        $shift = $this->shift();

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.store'), [
                'hari' => 1,
                'duty_shift_id' => $shift->id,
                'petugas' => [$this->teller->id, $this->tellerDua->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('duty_weekly_schedules', [
            'day_of_week' => 1,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->teller->id,
        ]);

        $this->assertDatabaseHas('duty_weekly_schedules', [
            'day_of_week' => 1,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->tellerDua->id,
        ]);

        // Tidak membuat jadwal bertanggal.
        $this->assertDatabaseCount('duty_schedules', 0);
    }

    public function test_halaman_jadwal_menampilkan_tabel_senin_sampai_jumat(): void
    {
        $shift = $this->shift();

        DutyWeeklySchedule::create([
            'day_of_week' => 1,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->teller->id,
            'created_by' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jadwal'))
            ->assertOk()
            ->assertSee('Jadwal Jaga Mingguan')
            ->assertSee('Senin')
            ->assertSee('Selasa')
            ->assertSee('Rabu')
            ->assertSee('Kamis')
            ->assertSee('Jumat')
            // Sabtu & Minggu tidak ada (sekolah libur).
            ->assertDontSee('>Sabtu<', false)
            ->assertDontSee('>Minggu<', false)
            ->assertSee('Budi Teller')
            ->assertSee('X TKJ 1')
            ->assertSee('berlaku setiap minggu');
    }

    public function test_jadwal_yang_sama_tidak_dibuat_dobel(): void
    {
        $shift = $this->shift();

        foreach ([1, 2] as $i) {
            $this->actingAs($this->supervisor)
                ->post(route('supervisor.jadwal.store'), [
                    'hari' => 1,
                    'duty_shift_id' => $shift->id,
                    'petugas' => [$this->teller->id],
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 1)->count());
    }

    public function test_jadwal_mingguan_dipakai_untuk_absensi_teller(): void
    {
        // 09 November 2026 = Senin.
        $this->travelTo(Carbon::parse('2026-11-09 08:00:00'));

        $shift = $this->shift();
        $shiftLain = $this->shift('Shift 2', '10:00', '12:00');

        DutyWeeklySchedule::create([
            'day_of_week' => 1,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->teller->id,
            'created_by' => $this->supervisor->id,
        ]);

        $this->actingAs($this->teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee($shift->label());

        // Absen pada shift yang tidak terjadwal ditolak.
        $this->actingAs($this->teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shiftLain->id])
            ->assertSessionHasErrors('absensi');

        // Absen pada shift yang terjadwal berhasil.
        $this->actingAs($this->teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('duty_attendances', [
            'user_id' => $this->teller->id,
            'duty_shift_id' => $shift->id,
            'status' => 'hadir',
        ]);
    }

    public function test_hari_sabtu_dan_minggu_tidak_bisa_dijadwalkan(): void
    {
        $shift = $this->shift();

        foreach ([6, 7] as $hari) {
            $this->actingAs($this->supervisor)
                ->post(route('supervisor.jadwal.store'), [
                    'hari' => $hari,
                    'duty_shift_id' => $shift->id,
                    'petugas' => [$this->teller->id],
                ])
                ->assertSessionHasErrors('hari');
        }

        $this->assertDatabaseCount('duty_weekly_schedules', 0);
    }

    public function test_jadwal_weekend_tidak_dipakai_untuk_absensi(): void
    {
        // 07 November 2026 = Sabtu (jadwal sekolah libur).
        $this->travelTo(Carbon::parse('2026-11-07 08:00:00'));

        $shift = $this->shift();

        // Data lama hari Sabtu (bila masih tersisa) tidak boleh dipakai.
        DutyWeeklySchedule::create([
            'day_of_week' => 6,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->teller->id,
            'created_by' => $this->supervisor->id,
        ]);

        $this->assertCount(0, DutyWeeklySchedule::forActiveDate(today(), $this->teller->id));

        $this->actingAs($this->teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Tidak ada jadwal')
            ->assertDontSee($shift->label());
    }

    public function test_supervisor_menghapus_petugas_dari_jadwal(): void
    {
        $shift = $this->shift();

        $jadwal = DutyWeeklySchedule::create([
            'day_of_week' => 2,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->teller->id,
            'created_by' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.destroy', $jadwal->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('duty_weekly_schedules', 0);
    }

    public function test_supervisor_belum_absen_memakai_jadwal_mingguan(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 07:30:00')); // Selasa

        $shift = $this->shift();

        DutyWeeklySchedule::create([
            'day_of_week' => 2,
            'duty_shift_id' => $shift->id,
            'user_id' => $this->teller->id,
            'created_by' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.absensi'))
            ->assertOk()
            ->assertSee('Terjadwal Hari Ini, Belum Absen')
            ->assertSee('Budi Teller');
    }
}
