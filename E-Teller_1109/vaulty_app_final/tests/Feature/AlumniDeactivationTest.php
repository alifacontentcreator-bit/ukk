<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario alumni deactivation test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perintah alumni hanya menonaktifkan akun nasabah. Akun, rekening, dan
 * seluruh riwayat transaksi wajib tetap ada agar Jurnal Audit Supervisor
 * lengkap dari dulu sampai sekarang.
 */
class AlumniDeactivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'transactions', 'accounts', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status', 20)->default('active');
            $table->string('email')->nullable()->unique();
            $table->string('nis', 30)->nullable();
            $table->string('kelas', 30)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('alamat')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedSmallInteger('angkatan')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('account_number', 30);
            $table->string('pin');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('product_type', 30)->default('tabungan_siswa');
            $table->string('qr_token', 80)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id');
            $table->foreignId('user_id');
            $table->string('transaction_code')->unique();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('success');
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
            $table->timestamps();
        });
    }

    public function test_alumni_dinonaktifkan_dan_riwayat_transaksi_tetap_tersimpan(): void
    {
        $alumni = $this->createAlumni(now()->year - 4);

        $accountId = $this->createAccount($alumni);

        DB::table('transactions')->insert([
            'account_id' => $accountId,
            'user_id' => $alumni->id,
            'transaction_code' => 'TRX-ALUMNI-0001',
            'type' => 'deposit',
            'amount' => 75000,
            'status' => 'success',
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ]);

        $this->artisan('akun:nonaktifkan-alumni')->assertSuccessful();

        // Hanya status yang berubah; akun dan riwayat transaksi tetap utuh.
        $this->assertSame('blocked', $alumni->fresh()->status);
        $this->assertDatabaseHas('users', ['id' => $alumni->id]);
        $this->assertDatabaseHas('accounts', ['id' => $accountId]);
        $this->assertDatabaseHas('transactions', ['transaction_code' => 'TRX-ALUMNI-0001']);
        $this->assertSame(1, DB::table('transactions')->where('account_id', $accountId)->count());
    }

    public function test_dry_run_tidak_mengubah_status(): void
    {
        $alumni = $this->createAlumni(now()->year - 4);

        $this->artisan('akun:nonaktifkan-alumni', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('active', $alumni->fresh()->status);
    }

    public function test_siswa_yang_belum_lulus_tetap_aktif(): void
    {
        $siswa = $this->createAlumni(now()->year);

        $this->artisan('akun:nonaktifkan-alumni')->assertSuccessful();

        $this->assertSame('active', $siswa->fresh()->status);
    }

    private function createAlumni(int $angkatan): User
    {
        return User::create([
            'name' => 'Nasabah Alumni',
            'username' => 'alumni-' . $angkatan,
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
            'nis' => '12345',
            'kelas' => 'XII TKJ 1',
            'angkatan' => $angkatan,
        ]);
    }

    private function createAccount(User $customer): int
    {
        return DB::table('accounts')->insertGetId([
            'user_id' => $customer->id,
            'account_number' => 'TKJ-2022-01-01',
            'pin' => bcrypt('123456'),
            'balance' => 75000,
            'product_type' => 'tabungan_siswa',
            'qr_token' => 'test-qr-token-alumni',
            'status' => 'active',
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ]);
    }
}
