<?php

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
 * Pengujian fitur Absensi Jaga Teller.
 *
 * Tes ini membutuhkan koneksi MySQL ke database yang namanya berakhiran _test.
 * Guard di TestCase::beforeRefreshingDatabase() akan skip semua tes bila nama DB tidak sesuai.
 */
class TeachersAttendanceTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────── helpers ────────────────────────────

    /** Buat teller dengan shift yang terjadwal hari ini dan waktu server dalam jendela. */
    private function setupTeller(array $shiftAttrs = []): array
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $shift  = DutyShift::factory()->create(array_merge([
            'start_time' => '07:00:00',
            'end_time'   => '09:30:00',
            'is_active'  => true,
        ], $shiftAttrs));
        DutyWeeklySchedule::factory()->create([
            'user_id'       => $teller->id,
            'duty_shift_id' => $shift->id,
            'day_of_week'   => Carbon::now()->dayOfWeekIso,
        ]);
        return [$teller, $shift];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time di dalam jendela shift (08:00, shift 07:00–09:30, toleransi 06:45)
        Carbon::setTestNow('2026-11-10 08:00:00'); // Senin
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─────────────────── A. Absen Masuk (checkIn) ───────────────────
    #[Test]
    public function check_in_berhasil_dan_tercatat_dengan_waktu_server(): void
    {
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $a = DutyAttendance::first();
        $this->assertNotNull($a);
        $this->assertEquals(today()->toDateString(), $a->duty_date->toDateString());
        $this->assertEquals('hadir', $a->status);
        $this->assertNotNull($a->check_in_at);
    }
    #[Test]
    public function field_status_dari_teller_ditolak(): void
    {
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), [
                'duty_shift_id' => $shift->id,
                'status'        => 'sakit',
            ])
            ->assertSessionHasErrors(['status']);

        $this->assertDatabaseCount('duty_attendances', 0);
    }
    public function test_field_duty_date_dari_teller_ditolak(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $shift = DutyShift::create([
            'name' => 'Shift 1',
            'start_time' => '07:00:00',
            'end_time' => '09:30:00',
            'is_active' => true,
        ]);
        DutyWeeklySchedule::create([
            'user_id' => $teller->id,
            'duty_shift_id' => $shift->id,
            'day_of_week' => Carbon::now()->dayOfWeekIso,
        ]);

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), [
                'duty_shift_id' => $shift->id,
                'duty_date'     => '2026-01-01',
                'check_in_at'   => '2026-01-01 08:00:00',
            ])
            ->assertSessionHasErrors(['duty_date', 'check_in_at']);

        $this->assertDatabaseCount('duty_attendances', 0);
    }
    public function test_field_tanggal_dan_waktu_dari_teller_ditolak_saat_check_out(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $shift = DutyShift::create([
            'name' => 'Shift 1',
            'start_time' => '07:00:00',
            'end_time' => '09:30:00',
            'is_active' => true,
        ]);
        DutyWeeklySchedule::create([
            'user_id' => $teller->id,
            'duty_shift_id' => $shift->id,
            'day_of_week' => Carbon::now()->dayOfWeekIso,
        ]);
        $attendance = DutyAttendance::create([
            'user_id' => $teller->id,
            'duty_shift_id' => $shift->id,
            'duty_date' => today(),
            'check_in_at' => now(),
            'status' => 'hadir',
        ]);

        $this->actingAs($teller)
            ->post(route('teller.absensi.pulang'), [
                'duty_shift_id' => $shift->id,
                'duty_date' => '2026-01-01',
                'check_out_at' => '2026-01-01 09:00:00',
            ])
            ->assertSessionHasErrors(['duty_date', 'check_out_at']);

        $this->assertNull($attendance->fresh()->check_out_at);
    }
    #[Test]
    public function check_in_ditolak_tanpa_jadwal(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $shift  = DutyShift::factory()->create(['is_active' => true]);
        // Tidak dibuat DutyWeeklySchedule

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }
    #[Test]
    public function check_in_ditolak_sebelum_jendela(): void
    {
        Carbon::setTestNow('2026-11-10 06:00:00'); // 45 menit sebelum toleransi (06:45)
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }
    #[Test]
    public function check_in_ditolak_setelah_jendela(): void
    {
        Carbon::setTestNow('2026-11-10 10:00:00'); // setelah 09:30
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }
    #[Test]
    public function check_in_ditolak_shift_nonaktif(): void
    {
        [$teller, $shift] = $this->setupTeller(['is_active' => false]);

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }
    #[Test]
    public function check_in_ditolak_shift_milik_teller_lain(): void
    {
        $tellerA = User::factory()->create(['role' => 'teller']);
        $tellerB = User::factory()->create(['role' => 'teller']);
        $shift   = DutyShift::factory()->create(['start_time' => '07:00:00', 'end_time' => '09:30:00', 'is_active' => true]);

        // Hanya teller B yang terjadwal
        DutyWeeklySchedule::factory()->create([
            'user_id'       => $tellerB->id,
            'duty_shift_id' => $shift->id,
            'day_of_week'   => Carbon::now()->dayOfWeekIso,
        ]);

        $this->actingAs($tellerA)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }
    #[Test]
    public function check_in_ganda_ditolak(): void
    {
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');

        $this->assertDatabaseCount('duty_attendances', 1);
    }

    // ─────────────────── B. Absen Pulang (checkOut) ─────────────────
    #[Test]
    public function check_out_berhasil_setelah_check_in(): void
    {
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $this->actingAs($teller)
            ->post(route('teller.absensi.pulang'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $this->assertNotNull(DutyAttendance::first()->check_out_at);
    }
    #[Test]
    public function check_out_ditolak_tanpa_check_in(): void
    {
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.pulang'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }
    public function teller_a_tidak_bisa_checkout_absensi_teller_b(): void
    {
        $tellerA = User::factory()->create(['role' => 'teller']);
        $tellerB = User::factory()->create(['role' => 'teller']);
        $shift   = DutyShift::factory()->create(['start_time' => '07:00:00', 'end_time' => '09:30:00', 'is_active' => true]);

        DutyWeeklySchedule::factory()->create([
            'user_id'       => $tellerB->id,
            'duty_shift_id' => $shift->id,
            'day_of_week'   => Carbon::now()->dayOfWeekIso,
        ]);

        // Teller B check-in
        $this->actingAs($tellerB)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        // Teller A mencoba check-out untuk Teller B → ditolak
        $this->actingAs($tellerA)
            ->post(route('teller.absensi.pulang'), ['duty_shift_id' => $shift->id])
            ->assertSessionHasErrors('absensi');
    }

    // ─────────────────── C. Supervisor ──────────────────────────────
    #[Test]
    public function supervisor_bisa_menandai_teller_yang_tidak_absen(): void
    {
        $spv    = User::factory()->create(['role' => 'supervisor']);
        $teller = User::factory()->create(['role' => 'teller']);
        $shift  = DutyShift::factory()->create(['is_active' => true]);

        $this->actingAs($spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'       => $teller->id,
                'duty_date'     => today()->toDateString(),
                'duty_shift_id' => $shift->id,
                'status'        => 'alpa',
                'note'          => 'Tidak ada kabar',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('duty_attendances', [
            'user_id'   => $teller->id,
            'status'    => 'alpa',
            'marked_by' => $spv->id,
        ]);
    }
    #[Test]
    public function supervisor_bisa_mengubah_status_dan_jejak_tercatat(): void
    {
        $spv    = User::factory()->create(['role' => 'supervisor']);
        $teller = User::factory()->create(['role' => 'teller']);
        $shift  = DutyShift::factory()->create(['is_active' => true]);

        // Tandai pertama (alpa)
        $this->actingAs($spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'       => $teller->id,
                'duty_date'     => today()->toDateString(),
                'duty_shift_id' => $shift->id,
                'status'        => 'alpa',
            ]);

        // Ubah ke izin (jejak harus ada)
        $this->actingAs($spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'       => $teller->id,
                'duty_date'     => today()->toDateString(),
                'duty_shift_id' => $shift->id,
                'status'        => 'izin',
            ])
            ->assertSessionHas('success');

        $a = DutyAttendance::first();
        $this->assertEquals('izin', $a->status);
        $this->assertEquals('alpa', $a->previous_status);
        $this->assertEquals($spv->id, $a->updated_by);
    }
    #[Test]
    public function teller_tidak_bisa_akses_route_tandai_supervisor(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);

        $this->actingAs($teller)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'   => $teller->id,
                'duty_date' => today()->toDateString(),
                'status'    => 'izin',
            ])
            ->assertStatus(403);
    }

    // ─────────────────── D. Tampilan View ───────────────────────────
    #[Test]
    public function view_teller_tidak_menampilkan_status_atau_badge_terlambat(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);

        $this->actingAs($teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertDontSee('Terlambat')
            ->assertDontSee('badge-warn')
            ->assertDontSee('Riwayat Kehadiran')
            ->assertDontSee('Filter tanggal')
            ->assertDontSee('value="sakit"')
            ->assertDontSee('value="alpa"')
            ->assertDontSee('value="izin"');
    }

    #[Test]
    public function supervisor_bisa_mengubah_status_teller_yang_sudah_absen_masuk(): void
    {
        [$teller, $shift] = $this->setupTeller();
        $spv = User::factory()->create(['role' => 'supervisor']);

        // Teller absen masuk seperti biasa (hanya jam, tanpa memilih status).
        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        // Supervisor yang berwenang mengubah status menjadi izin.
        $this->actingAs($spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'       => $teller->id,
                'duty_date'     => today()->toDateString(),
                'duty_shift_id' => $shift->id,
                'status'        => 'izin',
                'note'          => 'Surat izin dari orang tua',
            ])
            ->assertSessionHas('success');

        $a = DutyAttendance::first();
        $this->assertEquals('izin', $a->status);
        $this->assertEquals('hadir', $a->previous_status);
        $this->assertNotNull($a->check_in_at, 'Jam absen masuk Teller tidak boleh hilang.');
        $this->assertEquals($spv->id, $a->updated_by);
    }

    #[Test]
    public function halaman_supervisor_menyediakan_aksi_ubah_status(): void
    {
        [$teller, $shift] = $this->setupTeller();
        $spv = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $this->actingAs($spv)
            ->get(route('supervisor.absensi'))
            ->assertOk()
            ->assertSee('Ubah status')
            ->assertSee('Tandai / ubah status kehadiran');
    }

    #[Test]
    public function teller_tidak_bisa_mengisi_keterangan_kehadiran(): void
    {
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), [
                'duty_shift_id' => $shift->id,
                'note'          => 'sakit demam',
            ])
            ->assertSessionHasErrors(['note']);

        $this->assertDatabaseCount('duty_attendances', 0);
    }

    // ───────── E. Tabel "Kehadiran Hari Ini" (sesuai jadwal shift) ─────────

    /** Ambil potongan HTML khusus bagian tabel Kehadiran Hari Ini. */
    private function bagianHariIni(string $html): string
    {
        $mulai = strpos($html, 'Kehadiran Hari Ini');
        $selesai = strpos($html, 'Riwayat Kehadiran');

        $this->assertNotFalse($mulai, 'Judul "Kehadiran Hari Ini" tidak ditemukan.');
        $this->assertNotFalse($selesai, 'Judul "Riwayat Kehadiran" tidak ditemukan.');

        return substr($html, $mulai, $selesai - $mulai);
    }

    #[Test]
    public function tabel_hari_ini_hanya_memuat_petugas_yang_dijadwalkan_hari_itu(): void
    {
        // Waktu tes = Selasa, 10 November 2026.
        $spv = User::factory()->create(['role' => 'supervisor']);
        $tellerSenin = User::factory()->create(['role' => 'teller', 'name' => 'Adi Senin']);
        $tellerSelasa = User::factory()->create(['role' => 'teller', 'name' => 'Edo Selasa']);
        $shift = DutyShift::factory()->create(['start_time' => '07:00:00', 'end_time' => '09:30:00']);

        DutyWeeklySchedule::factory()->create([
            'user_id' => $tellerSenin->id, 'duty_shift_id' => $shift->id, 'day_of_week' => 1,
        ]);
        DutyWeeklySchedule::factory()->create([
            'user_id' => $tellerSelasa->id, 'duty_shift_id' => $shift->id, 'day_of_week' => 2,
        ]);

        $tabel = $this->bagianHariIni(
            $this->actingAs($spv)->get(route('supervisor.absensi'))->assertOk()->getContent()
        );

        // Hanya petugas jadwal Selasa yang muncul di tabel hari ini.
        $this->assertStringContainsString('Edo Selasa', $tabel);
        $this->assertStringNotContainsString('Adi Senin', $tabel);
        $this->assertStringContainsString('1 petugas terjadwal', $tabel);
    }

    #[Test]
    public function teller_yang_absen_masuk_otomatis_hadir_di_tabel_hari_ini(): void
    {
        [$teller, $shift] = $this->setupTeller();
        $spv = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $tabel = $this->bagianHariIni(
            $this->actingAs($spv)->get(route('supervisor.absensi'))->assertOk()->getContent()
        );

        $this->assertStringContainsString($teller->name, $tabel);
        $this->assertStringContainsString('Hadir', $tabel);
        $this->assertStringContainsString('otomatis dari absen Teller', $tabel);
        $this->assertStringNotContainsString('Belum absen', $tabel);
    }

    #[Test]
    public function spv_menandai_alpa_tanpa_konfirmasi_dan_izin_bila_teller_konfirmasi_langsung(): void
    {
        $spv = User::factory()->create(['role' => 'supervisor']);
        $shift = DutyShift::factory()->create(['start_time' => '07:00:00', 'end_time' => '09:30:00']);

        $tanpaKonfirmasi = User::factory()->create(['role' => 'teller', 'name' => 'Teller Tanpa Kabar']);
        $konfirmasi = User::factory()->create(['role' => 'teller', 'name' => 'Teller Konfirmasi']);

        foreach ([$tanpaKonfirmasi, $konfirmasi] as $teller) {
            DutyWeeklySchedule::factory()->create([
                'user_id' => $teller->id,
                'duty_shift_id' => $shift->id,
                'day_of_week' => Carbon::now()->dayOfWeekIso,
            ]);
        }

        // Tidak absen & tidak konfirmasi → Alpa.
        $this->actingAs($spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'       => $tanpaKonfirmasi->id,
                'duty_date'     => today()->toDateString(),
                'duty_shift_id' => $shift->id,
                'status'        => 'alpa',
            ])
            ->assertSessionHas('success');

        // Konfirmasi langsung ke SPV → Izin.
        $this->actingAs($spv)
            ->post(route('supervisor.absensi.tandai'), [
                'user_id'       => $konfirmasi->id,
                'duty_date'     => today()->toDateString(),
                'duty_shift_id' => $shift->id,
                'status'        => 'izin',
                'note'          => 'Konfirmasi langsung ke SPV',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('duty_attendances', ['user_id' => $tanpaKonfirmasi->id, 'status' => 'alpa']);
        $this->assertDatabaseHas('duty_attendances', ['user_id' => $konfirmasi->id, 'status' => 'izin']);

        $tabel = $this->bagianHariIni(
            $this->actingAs($spv)->get(route('supervisor.absensi'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('Teller Tanpa Kabar', $tabel);
        $this->assertStringContainsString('Teller Konfirmasi', $tabel);
        $this->assertStringContainsString('Alpa', $tabel);
        $this->assertStringContainsString('Izin', $tabel);
        $this->assertStringContainsString('2 petugas terjadwal', $tabel);
    }

    // ───────── F. Jam absen masuk, jadwal 7 hari & keamanan riwayat ─────────

    #[Test]
    public function tombol_absen_masuk_terkunci_di_luar_jam_shift_dan_alasannya_ditampilkan(): void
    {
        Carbon::setTestNow('2026-11-10 06:00:00'); // Selasa, sebelum jendela 06:45
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Absen dibuka pukul 06:45')
            ->assertSee('btn-disabled', false)
            ->assertSee('Absen masuk: ' . \App\Models\DutyShift::windowLabel($shift));
    }

    #[Test]
    public function tombol_absen_masuk_aktif_pada_jam_shift(): void
    {
        // Waktu tes default 08:00 → di dalam jendela 06:45-09:30.
        [$teller, $shift] = $this->setupTeller();

        $this->actingAs($teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertDontSee('Absen dibuka pukul')
            ->assertDontSee('Absen tutup pukul')
            ->assertSee('Absen Masuk');
    }

    #[Test]
    public function jadwal_tujuh_hari_menampilkan_jam_absen_masuk_per_tanggal(): void
    {
        [$teller, $shift] = $this->setupTeller();

        // Jadwal besok (Rabu) dengan shift yang sama.
        DutyWeeklySchedule::factory()->create([
            'user_id' => $teller->id,
            'duty_shift_id' => $shift->id,
            'day_of_week' => 3,
        ]);

        $this->actingAs($teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Jadwal 7 Hari ke Depan')
            ->assertSee('Absen Masuk')
            ->assertSee('06:45-09:30')
            ->assertSee(today()->addDay()->locale('id')->translatedFormat('l, d F Y'));
    }

    #[Test]
    public function teller_hanya_melihat_jadwal_dan_tidak_melihat_riwayat_kehadiran(): void
    {
        [$teller, $shift] = $this->setupTeller();

        // Riwayat kemarin tetap tersimpan, tetapi tidak ditampilkan ke Teller.
        DutyAttendance::create([
            'user_id' => $teller->id,
            'duty_shift_id' => $shift->id,
            'duty_date' => today()->subDay(),
            'check_in_at' => today()->subDay()->setTime(8, 0),
            'status' => 'hadir',
        ]);

        $this->actingAs($teller)
            ->get(route('teller.absensi'))
            ->assertOk()
            ->assertSee('Jadwal 7 Hari ke Depan')
            ->assertDontSee('Riwayat Kehadiran')
            ->assertDontSee(today()->subDay()->format('d/m/Y'));

        // Data tetap ada di database.
        $this->assertDatabaseCount('duty_attendances', 1);
    }

    #[Test]
    public function riwayat_kehadiran_tetap_tersimpan_walau_jadwal_mingguan_dihapus(): void
    {
        [$teller, $shift] = $this->setupTeller();
        $spv = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        $jadwal = DutyWeeklySchedule::where('user_id', $teller->id)->firstOrFail();

        $this->actingAs($spv)
            ->delete(route('supervisor.jadwal.destroy', $jadwal->id))
            ->assertSessionHasNoErrors();

        // Absensi hilang dari tabel hari ini (jadwal dihapus) tetapi riwayatnya utuh.
        $this->assertDatabaseCount('duty_attendances', 1);

        $this->actingAs($spv)
            ->get(route('supervisor.absensi'))
            ->assertOk()
            ->assertSee('Riwayat Kehadiran')
            ->assertSee($teller->name);
    }

    #[Test]
    public function shift_yang_sudah_punya_riwayat_absensi_tidak_bisa_dihapus(): void
    {
        [$teller, $shift] = $this->setupTeller();
        $spv = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($teller)
            ->post(route('teller.absensi.masuk'), ['duty_shift_id' => $shift->id])
            ->assertSessionHas('success');

        // Hapus jadwal mingguan agar yang diuji benar-benar penjagaan riwayat absensi.
        DutyWeeklySchedule::where('duty_shift_id', $shift->id)->delete();

        $this->actingAs($spv)
            ->delete(route('supervisor.shift.destroy', $shift->id))
            ->assertSessionHasErrors('jadwal');

        $this->assertDatabaseHas('duty_shifts', ['id' => $shift->id]);
        $this->assertDatabaseHas('duty_attendances', ['duty_shift_id' => $shift->id]);
    }
}

