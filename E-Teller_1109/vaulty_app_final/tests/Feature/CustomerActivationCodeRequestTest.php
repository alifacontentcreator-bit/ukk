<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConfiguredOtpMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class CustomerActivationCodeRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('customer_activation_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status')->default('active');
            $table->string('email')->nullable();
            $table->string('nis')->nullable();
            $table->string('nisn')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('customer_activation_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
        });

        // Observability: aktivasi yang berhasil menulis audit log.
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 100)->index();
            $table->string('module', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('method', 20)->nullable();
            $table->string('route', 255)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('status', 20)->default('success')->index();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_activation_code_is_emailed_and_stored_as_a_three_minute_hash(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');

        $userId = DB::table('users')->insertGetId([
            'name' => 'Student Test',
            'username' => 'student001',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => 'student@example.com',
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::findOrFail($userId);

        $sentCode = null;
        $this->mock(ConfiguredOtpMailer::class, function (MockInterface $mock) use (
            $user,
            &$sentCode
        ): void {
            $mock->shouldReceive('sendCustomerActivationCode')
                ->once()
                ->withArgs(function (
                    string $email,
                    string $name,
                    string $code
                ) use ($user, &$sentCode): bool {
                    $sentCode = $code;

                    return $email === $user->email
                        && $name === $user->name
                        && preg_match('/^[A-Z0-9]{8}$/', $code) === 1;
                });
        });

        $response = $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => $user->username,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('code', 'ACTIVATION_CODE_SENT')
            ->assertJsonPath('email', 'st***nt@example.com')
            ->assertJsonPath('expires_in', 180)
            ->assertJsonPath(
                'message',
                'Kode aktivasi sudah dikirim ke st***nt@example.com. Kode berlaku 3 menit.'
            );

        $this->assertNotNull($sentCode);

        $activationToken = DB::table('customer_activation_tokens')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($activationToken);
        $this->assertSame(
            hash('sha256', $sentCode),
            $activationToken->token_hash
        );
        $this->assertNotSame($sentCode, $activationToken->token_hash);
        $this->assertTrue(
            Carbon::parse($activationToken->expires_at)->equalTo(
                now()->addMinutes(3)
            )
        );

        Carbon::setTestNow(now()->addMinutes(3));

        $this->postJson('/api/v1/auth/activate', [
            'username' => $user->username,
            'activation_code' => (string) $sentCode,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'ACTIVATION_CODE_EXPIRED');
    }

    public function test_unknown_username_is_reported_as_not_found(): void
    {
        $response = $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => 'unknown-student',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('code', 'USER_NOT_FOUND')
            ->assertJsonPath(
                'message',
                'NIS unknown-student tidak ditemukan. Periksa kembali '
                    . 'nomor yang Anda masukkan.'
            );

        $this->assertDatabaseCount('customer_activation_tokens', 0);
    }

    public function test_already_activated_account_is_rejected(): void
    {
        DB::table('users')->insert([
            'name' => 'Already Active',
            'username' => 'student002',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => 'student002@example.com',
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => 'student002',
        ])->assertStatus(409)
            ->assertJsonPath('code', 'ALREADY_ACTIVATED');

        $this->assertDatabaseCount('customer_activation_tokens', 0);
    }

    public function test_account_without_registered_email_is_rejected(): void
    {
        DB::table('users')->insert([
            'name' => 'No Email',
            'username' => 'student003',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => null,
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => 'student003',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'EMAIL_NOT_REGISTERED')
            ->assertJsonPath(
                'message',
                'Akun student003 belum memiliki email terdaftar. Minta '
                    . 'Administrator mendaftarkan email Anda, lalu minta kode lagi.'
            );

        $this->assertDatabaseCount('customer_activation_tokens', 0);
    }

    public function test_nisn_can_be_used_instead_of_the_username(): void
    {
        DB::table('users')->insert([
            'name' => 'Student NISN',
            'username' => '0083336566',
            'nis' => '0083336566',
            'nisn' => '17790',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => 'student005@example.com',
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sentCode = null;
        $this->mock(ConfiguredOtpMailer::class, function (MockInterface $mock) use (
            &$sentCode
        ): void {
            $mock->shouldReceive('sendCustomerActivationCode')
                ->once()
                ->withArgs(function (
                    string $email,
                    string $name,
                    string $code
                ) use (&$sentCode): bool {
                    $sentCode = $code;

                    return $email === 'student005@example.com'
                        && $name === 'Student NISN'
                        && preg_match('/^[A-Z0-9]{8}$/', $code) === 1;
                });
        });

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => '17790',
        ])->assertAccepted()
            ->assertJsonPath('code', 'ACTIVATION_CODE_SENT');

        $this->assertNotNull($sentCode);
        $this->assertSame(
            1,
            DB::table('customer_activation_tokens')
                ->where('token_hash', hash('sha256', $sentCode))
                ->count()
        );
    }

    public function test_second_request_within_fifteen_seconds_is_throttled(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');

        DB::table('users')->insert([
            'name' => 'Student Cooldown',
            'username' => 'student004',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => 'student004@example.com',
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mock(ConfiguredOtpMailer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendCustomerActivationCode')->twice();
        });

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => 'student004',
        ])->assertAccepted()
            ->assertJsonPath('code', 'ACTIVATION_CODE_SENT');

        $throttled = $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => 'student004',
        ]);

        $throttled->assertStatus(429)
            ->assertJsonPath('code', 'ACTIVATION_CODE_RATE_LIMITED');

        $this->assertSame(
            'Tunggu 15 detik sebelum meminta kode lagi.',
            $throttled->json('message')
        );
        $this->assertLessThanOrEqual(15, $throttled->json('retry_after'));

        // Setelah 15 detik, permintaan kode diizinkan kembali.
        Carbon::setTestNow(now()->addSeconds(16));

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => 'student004',
        ])->assertAccepted()
            ->assertJsonPath('code', 'ACTIVATION_CODE_SENT');
    }

    public function test_new_code_does_not_invalidate_older_code_that_is_still_valid(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');

        DB::table('users')->insert([
            'name' => 'Student Resend',
            'username' => '17750',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => 'student006@example.com',
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $codes = [];
        $this->mock(ConfiguredOtpMailer::class, function (MockInterface $mock) use (
            &$codes
        ): void {
            $mock->shouldReceive('sendCustomerActivationCode')
                ->twice()
                ->withArgs(function (
                    string $email,
                    string $name,
                    string $code
                ) use (&$codes): bool {
                    $codes[] = $code;

                    return $email === 'student006@example.com';
                });
        });

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => '17750',
        ])->assertAccepted();

        Carbon::setTestNow(now()->addSeconds(16));

        $this->postJson('/api/v1/auth/request-activation-code', [
            'username' => '17750',
        ])->assertAccepted();

        $this->assertCount(2, $codes);

        // Kode dari email PERTAMA tetap dapat dipakai walau nasabah sudah
        // meminta kode baru (mis. menekan "kirim ulang" sebelum membaca email).
        $this->postJson('/api/v1/auth/activate', [
            'username' => '17750',
            'activation_code' => $codes[0],
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk()
            ->assertJsonPath('code', 'ACTIVATION_SUCCESS');
    }

    public function test_wrong_activation_code_reports_a_clear_message(): void
    {
        DB::table('users')->insert([
            'name' => 'Student Wrong Code',
            'username' => '17760',
            'password' => 'unused',
            'role' => 'customer',
            'status' => 'active',
            'email' => 'student007@example.com',
            'must_change_password' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/activate', [
            'username' => '17760',
            'activation_code' => 'ABCD1234',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'ACTIVATION_CODE_INVALID')
            ->assertJsonPath(
                'message',
                'Kode aktivasi tidak sesuai. Gunakan kode dari email terbaru '
                    . '(kode hanya berlaku 3 menit).'
            );
    }
}
