<?php
/* Tujuan file: Pengujian ini memastikan akun admin, supervisor, teller, dan nasabah TIDAK dapat dihapus, serta aturan aktivasi nasabah baru (nonaktif sampai aktivasi aplikasi mobile) berjalan sesuai ketentuan. */


namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'customer_activation_tokens', 'accounts', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('email')->nullable()->unique();
            $table->string('nis', 30)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('alamat')->nullable();
            $table->string('kelas', 30)->nullable();
            $table->string('jurusan', 30)->nullable();
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->string('kelas_urut', 5)->nullable();
            $table->unsignedSmallInteger('angkatan')->nullable();
            $table->string('jabatan', 5)->nullable();
            $table->string('photo')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('activation_deadline')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('account_number', 30);
            $table->string('pin');
            $table->boolean('pin_is_default')->default(true);
            $table->boolean('pin_reset_required')->default(false);
            $table->boolean('pin_blocked')->default(false);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('product_type', 30)->default('tabungan_siswa');
            $table->string('qr_token', 80)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('customer_activation_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action', 100);
            $table->string('module', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('method', 20)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('status', 20)->default('success');
            $table->timestamps();
        });
    }

    public function test_route_penghapusan_pengguna_tidak_tersedia_lagi(): void
    {
        $this->assertFalse(Route::has('admin.users.destroy'));
    }

    public function test_halaman_manajemen_pengguna_tidak_menyediakan_tombol_hapus(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $this->createUser('Teller Satu', 'teller01', 'teller');

        $customer = $this->createUser('Nasabah Baru', '12345', 'customer');
        $customer->forceFill([
            'status' => 'inactive',
            'must_change_password' => true,
            'activation_deadline' => now()->addDays(User::ACTIVATION_WINDOW_DAYS),
        ])->save();

        $response = $this->actingAs($admin)->get(route('admin.users'));

        $response->assertOk();
        $response->assertSee('tidak dapat dihapus');
        $response->assertSee('Menunggu aktivasi');
        $response->assertDontSee('Hapus ');
    }

    public function test_nasabah_belum_aktivasi_tidak_dapat_diaktifkan_manual_oleh_admin(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Baru', '12345', 'customer');
        $customer->forceFill([
            'status' => 'inactive',
            'must_change_password' => true,
            'activation_deadline' => now()->addDays(User::ACTIVATION_WINDOW_DAYS),
        ])->save();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $customer));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'inactive']);
    }

    public function test_buka_blokir_nasabah_belum_aktivasi_mengembalikan_status_nonaktif(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Alumni', '54321', 'customer', 'blocked');
        $customer->forceFill([
            'must_change_password' => true,
            'activation_deadline' => now()->subDays(5),
        ])->save();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $customer));

        $response->assertSessionHas('success');
        $this->assertSame('inactive', $customer->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_akun_nasabah_sudah_aktivasi_dinonaktifkan_bukan_dihapus(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Aktif', '99999', 'customer');

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $customer))
            ->assertSessionHas('success');

        $this->assertSame('blocked', $customer->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $customer))
            ->assertSessionHas('success');

        $this->assertSame('active', $customer->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_akun_petugas_diblokir_dan_dibuka_tanpa_dihapus(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $teller = $this->createUser('Teller Lama', 'teller02', 'teller');

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $teller))
            ->assertSessionHas('success');

        $this->assertSame('blocked', $teller->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-status', $teller))
            ->assertSessionHas('success');

        $this->assertSame('active', $teller->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $teller->id]);
    }

    public function test_form_ubah_pengguna_tidak_boleh_mengaktifkan_nasabah_belum_aktivasi(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Baru', '12345', 'customer');
        $customer->forceFill([
            'status' => 'inactive',
            'must_change_password' => true,
            'activation_deadline' => now()->addDays(User::ACTIVATION_WINDOW_DAYS),
        ])->save();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $customer), [
            'name' => $customer->name,
            'username' => $customer->username,
            'role' => 'customer',
            'status' => 'active',
            'email' => 'nasabah-baru@example.test',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame('inactive', $customer->fresh()->status);
    }

    public function test_form_ubah_pengguna_tetap_bisa_memblokir_akun_tanpa_menghapus(): void
    {
        $admin = $this->createUser('Administrator', 'admin', 'admin');
        $customer = $this->createUser('Nasabah Aktif', '54321', 'customer');

        $response = $this->actingAs($admin)->put(route('admin.users.update', $customer), [
            'name' => $customer->name,
            'username' => $customer->username,
            'role' => 'customer',
            'status' => 'blocked',
            'email' => 'nasabah-aktif@example.test',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('blocked', $customer->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    private function createUser(
        string $name,
        string $username,
        string $role,
        string $status = 'active'
    ): User {
        return User::create([
            'name' => $name,
            'username' => $username,
            'password' => 'password',
            'role' => $role,
            'status' => $status,
        ]);
    }
}
