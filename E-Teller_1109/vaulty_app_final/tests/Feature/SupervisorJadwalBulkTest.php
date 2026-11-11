<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario supervisor jadwal bulk test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\DutyAttendance;
use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ubah & hapus jadwal jaga oleh Supervisor, untuk hari tertentu maupun semua hari.
 */
class SupervisorJadwalBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $budi;

    private User $sari;

    private DutyShift $pagi;

    private DutyShift $siang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'SPV Jadwal']);
        $this->budi = User::factory()->create(['role' => 'teller', 'name' => 'Budi Jadwal', 'kelas' => 'X TKJ 1']);
        $this->sari = User::factory()->create(['role' => 'teller', 'name' => 'Sari Jadwal', 'kelas' => 'XI AKL 2']);

        $this->pagi = DutyShift::create(['name' => 'Shift Pagi', 'start_time' => '07:00:00', 'end_time' => '09:30:00', 'is_active' => true]);
        $this->siang = DutyShift::create(['name' => 'Shift Siang', 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'is_active' => true]);

        // Senin: Budi (pagi) & Sari (siang). Selasa: Budi (pagi).
        DutyWeeklySchedule::create(['day_of_week' => 1, 'duty_shift_id' => $this->pagi->id, 'user_id' => $this->budi->id, 'created_by' => $this->supervisor->id]);
        DutyWeeklySchedule::create(['day_of_week' => 1, 'duty_shift_id' => $this->siang->id, 'user_id' => $this->sari->id, 'created_by' => $this->supervisor->id]);
        DutyWeeklySchedule::create(['day_of_week' => 2, 'duty_shift_id' => $this->pagi->id, 'user_id' => $this->budi->id, 'created_by' => $this->supervisor->id]);
    }

    public function test_ubah_shift_untuk_hari_tertentu_saja(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.bulk-update'), [
                'hari' => 1,
                'aksi' => 'shift',
                'duty_shift_id' => $this->siang->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // Senin: semua baris pindah ke shift siang (Budi & Sari).
        $this->assertSame(2, DutyWeeklySchedule::where('day_of_week', 1)->where('duty_shift_id', $this->siang->id)->count());

        // Selasa tidak berubah.
        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 2)->where('duty_shift_id', $this->pagi->id)->count());
    }

    public function test_ubah_shift_untuk_semua_hari(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.bulk-update'), [
                'hari' => 'all',
                'aksi' => 'shift',
                'duty_shift_id' => $this->siang->id,
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, DutyWeeklySchedule::where('duty_shift_id', $this->pagi->id)->count());
        $this->assertSame(3, DutyWeeklySchedule::where('duty_shift_id', $this->siang->id)->count());
    }

    public function test_ubah_shift_menggabungkan_baris_duplikat(): void
    {
        // Sari juga sudah terjadwal di shift siang hari Senin, jadi baris pagi
        // milik Sari (bila ada) akan digabung, bukan menjadi duplikat.
        DutyWeeklySchedule::create(['day_of_week' => 1, 'duty_shift_id' => $this->pagi->id, 'user_id' => $this->sari->id, 'created_by' => $this->supervisor->id]);

        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.bulk-update'), [
                'hari' => 1,
                'aksi' => 'shift',
                'duty_shift_id' => $this->siang->id,
            ])
            ->assertSessionHas('success');

        // Sari hanya punya satu baris pada Senin shift siang (tidak dobel).
        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 1)
            ->where('duty_shift_id', $this->siang->id)
            ->where('user_id', $this->sari->id)
            ->count());
    }

    public function test_atur_ulang_petugas_pada_shift_untuk_hari_tertentu(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.bulk-update'), [
                'hari' => 1,
                'aksi' => 'petugas',
                'duty_shift_id' => $this->pagi->id,
                'petugas' => [$this->sari->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // Budi diganti Sari pada Senin shift pagi.
        $this->assertSame(0, DutyWeeklySchedule::where('day_of_week', 1)
            ->where('duty_shift_id', $this->pagi->id)
            ->where('user_id', $this->budi->id)
            ->count());

        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 1)
            ->where('duty_shift_id', $this->pagi->id)
            ->where('user_id', $this->sari->id)
            ->count());
    }

    public function test_atur_ulang_petugas_untuk_semua_hari(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.bulk-update'), [
                'hari' => 'all',
                'aksi' => 'petugas',
                'duty_shift_id' => $this->pagi->id,
                'petugas' => [$this->budi->id, $this->sari->id],
            ])
            ->assertSessionHas('success');

        // Setiap hari (Senin–Jumat) punya 2 petugas pada shift pagi.
        $this->assertSame(10, DutyWeeklySchedule::where('duty_shift_id', $this->pagi->id)->count());
        $this->assertSame(5, DutyWeeklySchedule::where('duty_shift_id', $this->pagi->id)->where('user_id', $this->sari->id)->count());
    }

    public function test_atur_ulang_petugas_wajib_pilih_minimal_satu(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('supervisor.jadwal.bulk-update'), [
                'hari' => 1,
                'aksi' => 'petugas',
                'duty_shift_id' => $this->pagi->id,
                'petugas' => [],
            ])
            ->assertSessionHasErrors('petugas');
    }

    public function test_hapus_jadwal_hari_tertentu_saja(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), ['hari' => 1])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(0, DutyWeeklySchedule::where('day_of_week', 1)->count());

        // Hari lain tetap ada.
        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 2)->count());
    }

    public function test_hapus_semua_jadwal_semua_hari(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), ['hari' => 'all'])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('duty_weekly_schedules', 0);
    }

    public function test_hapus_jadwal_dengan_filter_shift_dan_petugas(): void
    {
        // Hapus hanya shift siang pada Senin → baris Sari terhapus, Budi tetap.
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), [
                'hari' => 1,
                'duty_shift_id' => $this->siang->id,
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 1)->count());
        $this->assertSame(1, DutyWeeklySchedule::where('day_of_week', 1)->where('user_id', $this->budi->id)->count());

        // Hapus hanya milik Budi pada Selasa.
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), [
                'hari' => 2,
                'petugas' => [$this->budi->id],
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, DutyWeeklySchedule::where('day_of_week', 2)->count());
    }

    public function test_hapus_jadwal_tidak_menghapus_riwayat_kehadiran(): void
    {
        DutyAttendance::create([
            'user_id' => $this->budi->id,
            'duty_shift_id' => $this->pagi->id,
            'duty_date' => today(),
            'check_in_at' => now(),
            'status' => 'hadir',
        ]);

        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), ['hari' => 'all'])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('duty_weekly_schedules', 0);
        $this->assertDatabaseCount('duty_attendances', 1);
    }

    public function test_hapus_menolak_hari_tidak_valid_dan_pilihan_kosong(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), ['hari' => 6])
            ->assertSessionHasErrors('hari');

        $this->assertDatabaseCount('duty_weekly_schedules', 3);

        // Kombinasi filter yang tidak cocok → pesan error, tidak ada yang terhapus.
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jadwal.bulk-destroy'), [
                'hari' => 5,
                'duty_shift_id' => $this->pagi->id,
            ])
            ->assertSessionHasErrors('jadwal');

        $this->assertDatabaseCount('duty_weekly_schedules', 3);
    }

    public function test_halaman_jadwal_menampilkan_form_ubah_dan_hapus(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jadwal'))
            ->assertOk()
            ->assertSee('Ubah / Hapus Jadwal')
            ->assertSee('Ubah Jadwal')
            ->assertSee('Hapus Jadwal')
            ->assertSee('Semua hari (Senin–Jumat)')
            ->assertSee('Ganti shift (petugas tetap)')
            ->assertSee('Atur ulang petugas pada shift')
            ->assertSee(route('supervisor.jadwal.bulk-update'), false)
            ->assertSee(route('supervisor.jadwal.bulk-destroy'), false);
    }
}
