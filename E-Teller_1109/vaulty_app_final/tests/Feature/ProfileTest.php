<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Uji fitur "Profil Saya" (semua peran).
 *
 * Skema dibuat manual (bukan RefreshDatabase) karena salah satu migrasi memakai
 * sintaks MySQL-only sehingga tidak bisa jalan di sqlite :memory:.
 */
class ProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('email')->nullable()->unique();
            $table->string('no_telp', 20)->nullable();
            $table->string('nis', 30)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('kelas', 30)->nullable();
            $table->string('photo')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function test_tamu_tidak_bisa_membuka_halaman_profil(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_halaman_profil_tampil_untuk_semua_peran(): void
    {
        $harapan = [
            'admin' => 'Administrator',
            'supervisor' => 'Supervisor',
            'teller' => 'Teller',
            'customer' => 'Nasabah',
        ];

        foreach ($harapan as $role => $label) {
            $user = $this->makeUser(['role' => $role, 'username' => 'user-' . $role]);

            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertSee('Profil Saya')
                ->assertSee($label);
        }
    }

    public function test_tidak_ada_unggah_atau_hapus_foto_pada_peran_mana_pun(): void
    {
        foreach (['admin', 'supervisor', 'teller', 'customer'] as $role) {
            $user = $this->makeUser(['role' => $role, 'username' => 'foto-' . $role]);

            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertSee('Foto profil hanya dapat diubah oleh Administrator.')
                ->assertDontSee('type="file"', false)
                ->assertDontSee('Hapus Foto', false)
                ->assertDontSee('Unggah Foto', false);
        }
    }

    public function test_bagian_email_hanya_untuk_teller_dan_supervisor(): void
    {
        $teller = $this->makeUser(['role' => 'teller', 'username' => 'teller-email', 'email' => 'teller@example.test']);
        $this->actingAs($teller)->get(route('profile.show'))->assertOk()->assertSee('Edit Email');

        $supervisor = $this->makeUser(['role' => 'supervisor', 'username' => 'sup-email', 'email' => 'sup@example.test']);
        $this->actingAs($supervisor)->get(route('profile.show'))->assertOk()->assertSee('Edit Email');

        $customer = $this->makeUser(['role' => 'customer', 'username' => 'cust-email', 'email' => 'cust@example.test']);
        $this->actingAs($customer)->get(route('profile.show'))->assertOk()->assertDontSee('Edit Email');

        $admin = $this->makeUser(['role' => 'admin', 'username' => 'admin-email']);
        $this->actingAs($admin)->get(route('profile.show'))->assertOk()->assertDontSee('Edit Email');
    }

    public function test_staf_bisa_mengubah_nama_dan_field_lain_diabaikan(): void
    {
        $user = $this->makeUser([
            'role' => 'teller',
            'username' => 'teller-nama',
            'nis' => '12345',
            'kelas' => 'XI RPL 1',
            'photo' => 'photos/lama.jpg',
            'email' => 'teller@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Nama Baru',
                // Field di bawah ini dikirim paksa (mis. lewat devtools) dan harus diabaikan.
                'role' => 'admin',
                'status' => 'blocked',
                'username' => 'diambil-alih',
                'nis' => '99999',
                'nip' => '88888',
                'kelas' => 'XII TKJ 9',
                'photo' => 'photos/baru.jpg',
                'balance' => 999999999,
                'pin' => '123456',
                'email' => 'diambil-alih@example.test',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('teller', $fresh->role);
        $this->assertSame('active', $fresh->status);
        $this->assertSame('teller-nama', $fresh->username);
        $this->assertSame('12345', $fresh->nis);
        $this->assertSame('XI RPL 1', $fresh->kelas);
        $this->assertSame('photos/lama.jpg', $fresh->photo);
        $this->assertSame('teller@example.test', $fresh->email);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile.name.updated',
        ]);
    }

    public function test_nasabah_tidak_bisa_mengubah_nama(): void
    {
        $customer = $this->makeUser([
            'role' => 'customer',
            'username' => 'cust-nama',
            'name' => 'Nama Resmi Sekolah',
        ]);

        $this->actingAs($customer)
            ->put(route('profile.update'), ['name' => 'Nama Palsu'])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors('name');

        $this->assertSame('Nama Resmi Sekolah', $customer->fresh()->name);
    }

    public function test_nama_wajib_diisi_dan_maksimal_100_karakter(): void
    {
        $user = $this->makeUser(['role' => 'supervisor', 'username' => 'sup-nama']);

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => str_repeat('a', 101)])
            ->assertSessionHasErrors('name');
    }

    public function test_ubah_email_ditolak_bila_sandi_saat_ini_salah(): void
    {
        $user = $this->makeUser([
            'role' => 'teller',
            'username' => 'teller-e1',
            'email' => 'lama@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.email'), [
                'email' => 'baru@example.test',
                'current_password' => 'sandi-salah',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('lama@example.test', $user->fresh()->email);
    }

    public function test_ubah_email_menolak_email_milik_pengguna_lain_dengan_pesan_umum(): void
    {
        $this->makeUser([
            'role' => 'teller',
            'username' => 'teller-lain',
            'email' => 'dipakai@example.test',
        ]);

        $user = $this->makeUser([
            'role' => 'teller',
            'username' => 'teller-e2',
            'email' => 'milik-saya@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.email'), [
                'email' => 'dipakai@example.test',
                'current_password' => 'rahasia-lama',
            ])
            ->assertSessionHasErrors(['email' => 'Email tidak dapat digunakan.']);

        $this->assertSame('milik-saya@example.test', $user->fresh()->email);
    }

    public function test_ubah_email_menolak_email_milik_pengguna_lain_walau_huruf_besar(): void
    {
        $this->makeUser([
            'role' => 'supervisor',
            'username' => 'sup-lain',
            'email' => 'dipakai@example.test',
        ]);

        $user = $this->makeUser([
            'role' => 'teller',
            'username' => 'teller-e2b',
            'email' => 'milik-saya@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.email'), [
                'email' => '  Dipakai@Example.Test  ',
                'current_password' => 'rahasia-lama',
            ])
            ->assertSessionHasErrors(['email' => 'Email tidak dapat digunakan.']);

        $this->assertSame('milik-saya@example.test', $user->fresh()->email);
    }

    public function test_ubah_email_berhasil_huruf_kecil_dan_tercatat_di_audit(): void
    {
        $user = $this->makeUser([
            'role' => 'supervisor',
            'username' => 'sup-e3',
            'email' => 'lama@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.email'), [
                'email' => 'Baru@Example.Test',
                'current_password' => 'rahasia-lama',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $this->assertSame('baru@example.test', $user->fresh()->email);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'profile.email.updated',
        ]);
    }

    public function test_nasabah_tidak_bisa_mengubah_email_lewat_request(): void
    {
        $customer = $this->makeUser([
            'role' => 'customer',
            'username' => 'cust-e4',
            'email' => 'cust@example.test',
        ]);

        $this->actingAs($customer)
            ->put(route('profile.email'), [
                'email' => 'lain@example.test',
                'current_password' => 'rahasia-lama',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('cust@example.test', $customer->fresh()->email);
    }

    public function test_dropdown_topbar_berisi_profil_saya_dan_keluar(): void
    {
        $user = $this->makeUser(['role' => 'supervisor', 'username' => 'sup-menu']);

        $html = $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-user-menu-toggle', $html);
        $this->assertStringContainsString('aria-haspopup="true"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString(route('profile.show'), $html);
        $this->assertStringContainsString('Keluar', $html);

        // Label peran harus muncul di topbar juga (crumb + tombol pengguna).
        $this->assertGreaterThanOrEqual(2, substr_count($html, '<span>Supervisor</span>'));
    }

    public function test_tidak_ada_parameter_id_yang_bisa_membuka_profil_orang_lain(): void
    {
        $userA = $this->makeUser(['role' => 'teller', 'username' => 'teller-a', 'name' => 'Pengguna A']);
        $userB = $this->makeUser(['role' => 'teller', 'username' => 'teller-b', 'name' => 'Pengguna B']);

        $this->actingAs($userA)
            ->get(route('profile.show', ['id' => $userB->id, 'user_id' => $userB->id]))
            ->assertOk()
            ->assertSee('Pengguna A')
            ->assertDontSee('Pengguna B');
    }

    public function test_ubah_sandi_ditolak_bila_sandi_saat_ini_salah(): void
    {
        $user = $this->makeUser(['role' => 'teller', 'username' => 'teller-p1']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'sandi-salah',
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'sandi-baru-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('rahasia-lama', $user->fresh()->password));
    }

    public function test_ubah_sandi_menolak_sandi_baru_sama_dengan_sandi_lama(): void
    {
        $user = $this->makeUser(['role' => 'teller', 'username' => 'teller-p2']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'rahasia-lama',
                'password' => 'rahasia-lama',
                'password_confirmation' => 'rahasia-lama',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('rahasia-lama', $user->fresh()->password));
    }

    public function test_ubah_sandi_menolak_konfirmasi_yang_tidak_cocok(): void
    {
        $user = $this->makeUser(['role' => 'customer', 'username' => 'cust-p3']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'rahasia-lama',
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'beda-123456',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('rahasia-lama', $user->fresh()->password));
    }

    public function test_ubah_sandi_berhasil_mengakhiri_sesi_lain_dan_audit_tanpa_nilai_sandi(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser(['role' => 'teller', 'username' => 'teller-p4']);

        DB::table('sessions')->insert([
            'id' => 'sesi-perangkat-lain',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.9',
            'user_agent' => 'Perangkat lain',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'rahasia-lama',
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'sandi-baru-123',
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('sandi-baru-123', $fresh->password));
        $this->assertNotNull($fresh->password_changed_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-perangkat-lain']);

        $log = AuditLog::where('user_id', $user->id)
            ->where('action', 'profile.password.updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('sandi-baru-123', $log->description);
        $this->assertSame(1, AuditLog::where('action', 'profile.password.updated')->count());
    }

    public function test_percobaan_ubah_sandi_dibatasi_throttle(): void
    {
        $user = $this->makeUser(['role' => 'teller', 'username' => 'teller-p5']);

        $payload = [
            'current_password' => 'sandi-salah',
            'password' => 'sandi-baru-123',
            'password_confirmation' => 'sandi-baru-123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->put(route('profile.password'), $payload)
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->put(route('profile.password'), $payload)
            ->assertStatus(429);
    }

    private function makeUser(array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'name' => 'Pengguna ' . $counter,
            'username' => 'pengguna' . $counter,
            'password' => 'rahasia-lama',
            'role' => 'teller',
            'status' => 'active',
        ], $overrides));
    }
}
