<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario absensi jaga flow test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\DutyAttendance;
use App\Models\DutyShift;
use App\Models\DutyWeeklySchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Simulasi penuh satu hari kerja (Senin): 5 teller terjadwal pada 2 shift,
 * 3 teller absen masuk sendiri, 2 teller ditandai Supervisor.
 *
 * Tujuan: memastikan
 * 1) tombol Absen Masuk hanya bisa diklik pada hari & jam shift-nya,
 * 2) Teller hanya melihat jadwal 7 hari ke depan + jam absen masuk,
 * 3) riwayat kehadiran hanya untuk Supervisor dan tidak pernah hilang,
 * 4) tidak ada notifikasi ganda / error 500.
 */
class AbsensiJagaFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $spv;

    private DutyShift $shiftPagi;

    private DutyShift $shiftSiang;

    /** @var array<int, User> */
    private array $tellers = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 9 November 2026 pukul 07:30 → di dalam jendela shift pagi (06:45-09:30).
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:30:00'));

        $this->spv = User::factory()->create(['role' => 'supervisor', 'name' => 'SPV Simulasi']);

        $this->shiftPagi = DutyShift::create([
            'name' => 'Shift Pagi', 'start_time' => '07:00:00', 'end_time' => '09:30:00', 'is_active' => true,
        ]);
        $this->shiftSiang = DutyShift::create([
            'name' => 'Shift Siang', 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'is_active' => true,
        ]);

        $nama = ['Adi Simulasi', 'Mira Simulasi', 'Alex Simulasi', 'Cira Simulasi', 'Dewi Simulasi'];

        foreach ($nama as $i => $n) {
            $teller = User::factory()->create(['role' => 'teller', 'name' => $n, 'kelas' => 'XI AKL 1']);

            DutyWeeklySchedule::create([
                'day_of_week' => 1, // Senin
                'duty_shift_id' => $i < 3 ? $this->shiftPagi->id : $this->shiftSiang->id,
                'user_id' => $teller->id,
                'created_by' => $this->spv->id,
            ]);

            $this->tellers[$i] = $teller;
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Potongan HTML khusus bagian tabel Kehadiran Hari Ini. */
    private function bagianHariIni(string $html): string
    {
        $mulai = strpos($html, 'Kehadiran Hari Ini');
        $selesai = strpos($html, 'Riwayat Kehadiran');

        $this->assertNotFalse($mulai, 'Bagian "Kehadiran Hari Ini" tidak ditemukan.');
        $this->assertNotFalse($selesai, 'Bagian "Riwayat Kehadiran" tidak ditemukan.');

        return substr($html, $mulai, $selesai - $mulai);
    }

    #[Test]
    public function tabel_hari_ini_hanya_memuat_petugas_shift_senin(): void
    {
        // Teller tambahan yang dijadwalkan Selasa tidak boleh muncul di tabel Senin.
        $tellerSelasa = User::factory()->create(['role' => 'teller', 'name' => 'Edo Selasa']);
        DutyWeeklySchedule::create([
            'day_of_week' => 2,
            'duty_shift_id' => $this->shiftPagi->id,
            'user_id' => $tellerSelasa->id,
            'created_by' => $this->spv->id,
        ]);

        $html = $this->actingAs($this->spv)->get(route('supervisor.absensi'))->assertOk()->getContent();
        $tabel = $this->bagianHariIni($html);

        foreach ($this->tellers as $teller) {
            $this->assertStringContainsString($teller->name, $tabel);
        }

        $this->assertStringNotContainsString('Edo Selasa', $tabel);
        $this->assertStringContainsString('5 petugas terjadwal', $tabel);
    }

    #[Test]
    public function tiga_teller_absen_masuk_tercatat_hadir_dan_dua_sisanya_belum_absen(): void
    {
        // Hanya 3 teller shift pagi yang bisa absen pada pukul 07:30.
        foreach ([0, 1, 2] as $i) {
            $this->actingAs($this->tellers[$i])
                ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
                ->assertSessionHasNoErrors();
        }

        $html = $this->actingAs($this->spv)->get(route('supervisor.absensi'))->assertOk()->getContent();
        $tabel = $this->bagianHariIni($html);

        // 3 hadir otomatis, 2 (shift siang) belum absen.
        $this->assertSame(3, substr_count($tabel, 'otomatis dari absen Teller'));
        $this->assertSame(2, substr_count($tabel, 'Belum absen'));
        $this->assertSame(3, DutyAttendance::where('status', 'hadir')->count());
    }

    #[Test]
    public function teller_shift_siang_belum_bisa_absen_pada_pagi_hari(): void
    {
        // Tombol terkunci + alasan jendela absen ditampilkan.
        $this->actingAs($this->tellers[3])
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Absen dibuka pukul 09:45')
            ->assertSee('btn-disabled', false);

        // Memaksa POST pun tetap ditolak server.
        $this->actingAs($this->tellers[3])
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftSiang->id])
            ->assertSessionHasErrors('absensi');

        $this->assertDatabaseCount('duty_attendances', 0);
    }

    #[Test]
    public function spv_menandai_izin_dan_alpa_lalu_semua_tercatat(): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->actingAs($this->tellers[$i])
                ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
                ->assertSessionHasNoErrors();
        }

        // Cira konfirmasi langsung ke SPV → Izin.
        $this->actingAs($this->spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id' => $this->tellers[3]->id,
                'duty_date' => today()->toDateString(),
                'duty_shift_id' => $this->shiftSiang->id,
                'status' => 'izin',
                'note' => 'Konfirmasi langsung ke SPV',
            ])
            ->assertSessionHasNoErrors();

        // Dewi tanpa kabar → Alpa.
        $this->actingAs($this->spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id' => $this->tellers[4]->id,
                'duty_date' => today()->toDateString(),
                'duty_shift_id' => $this->shiftSiang->id,
                'status' => 'alpa',
            ])
            ->assertSessionHasNoErrors();

        // 3 hadir + 1 izin + 1 alpa, tidak ada record ganda.
        $this->assertDatabaseCount('duty_attendances', 5);
        $this->assertDatabaseHas('duty_attendances', ['user_id' => $this->tellers[3]->id, 'status' => 'izin']);
        $this->assertDatabaseHas('duty_attendances', ['user_id' => $this->tellers[4]->id, 'status' => 'alpa']);

        $html = $this->actingAs($this->spv)->get(route('supervisor.absensi'))->assertOk()->getContent();
        $tabel = $this->bagianHariIni($html);

        $this->assertStringContainsString('Konfirmasi langsung ke SPV', $tabel);
        $this->assertSame(0, substr_count($tabel, 'Belum absen'));
    }

    #[Test]
    public function halaman_teller_hanya_jadwal_dan_tidak_memuat_status_maupun_riwayat(): void
    {
        // Simpan riwayat kemarin agar terbukti tidak ikut tampil.
        DutyAttendance::create([
            'user_id' => $this->tellers[0]->id,
            'duty_shift_id' => $this->shiftPagi->id,
            'duty_date' => today()->subDay(),
            'check_in_at' => today()->subDay()->setTime(7, 5),
            'status' => 'hadir',
        ]);

        $this->actingAs($this->tellers[0])
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Jadwal 7 Hari ke Depan')
            ->assertSee('Absen Masuk')
            ->assertDontSee('Riwayat Kehadiran')
            ->assertDontSee('Hadir')
            ->assertDontSee('Izin')
            ->assertDontSee('Sakit')
            ->assertDontSee('Alpa')
            ->assertDontSee('badge-danger', false)
            ->assertDontSee(today()->subDay()->format('d/m/Y'));

        $this->assertDatabaseCount('duty_attendances', 1);
    }

    #[Test]
    public function notifikasi_sukses_hanya_muncul_sekali_setelah_absen_masuk(): void
    {
        $this->actingAs($this->tellers[0])
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
            ->assertSessionHas('success');

        $html = $this->actingAs($this->tellers[0])->get(route('teller.absensi'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'alert alert-success'), 'Notifikasi sukses tidak boleh ganda.');

        // Request berikutnya tidak menampilkan notifikasi yang sama lagi (flash sekali pakai).
        $htmlLagi = $this->actingAs($this->tellers[0])->get(route('teller.absensi'))->assertOk()->getContent();
        $this->assertSame(0, substr_count($htmlLagi, 'alert alert-success'));
    }

    #[Test]
    public function absen_dua_kali_tidak_membuat_record_ganda(): void
    {
        $this->actingAs($this->tellers[0])
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->tellers[0])
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
            ->assertSessionHasErrors('absensi');

        $this->assertDatabaseCount('duty_attendances', 1);
    }

    #[Test]
    public function riwayat_spv_tetap_utuh_dan_shift_tidak_bisa_dihapus(): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->actingAs($this->tellers[$i])
                ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $this->shiftPagi->id])
                ->assertSessionHasNoErrors();
        }

        // Supervisor menghapus jadwal mingguan → absensi hari ini tidak lagi di tabel,
        // tetapi riwayatnya tetap ada.
        DutyWeeklySchedule::where('day_of_week', 1)->delete();

        $this->assertDatabaseCount('duty_attendances', 3);

        $html = $this->actingAs($this->spv)->get(route('supervisor.absensi'))->assertOk()->getContent();
        $this->assertStringContainsString('Tidak ada petugas yang dijadwalkan shift hari ini.', $this->bagianHariIni($html));
        $this->assertStringContainsString('Riwayat Kehadiran', $html);
        $this->assertStringContainsString('Adi Simulasi', $html);

        // Shift yang punya riwayat absensi tidak boleh dihapus.
        $this->actingAs($this->spv)
            ->delete(route('supervisor.shift.destroy', $this->shiftPagi->id))
            ->assertSessionHasErrors('jadwal');

        $this->assertDatabaseHas('duty_shifts', ['id' => $this->shiftPagi->id]);
        $this->assertDatabaseCount('duty_attendances', 3);
    }

    #[Test]
    public function pada_hari_minggu_teller_tetap_melihat_jadwal_senin_sampai_jumat(): void
    {
        // Minggu, 8 November 2026: tidak ada shift hari ini, tetapi jadwal
        // Senin–Jumat tetap tampil di tabel 7 hari ke depan.
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        $this->actingAs($this->tellers[0])
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Tidak ada jadwal jaga hari ini')
            ->assertSee('Jadwal 7 Hari ke Depan')
            ->assertSee(Carbon::parse('2026-11-09')->locale('id')->translatedFormat('l, d F Y')) // Senin
            ->assertSee('06:45-09:30');

        // Halaman SPV juga aman: tabel hari ini kosong, riwayat tetap tampil.
        $this->actingAs($this->spv)
            ->get(route('supervisor.absensi'))
            ->assertOk()
            ->assertSee('Tidak ada petugas yang dijadwalkan shift hari ini.')
            ->assertSee('Riwayat Kehadiran');
    }
}
