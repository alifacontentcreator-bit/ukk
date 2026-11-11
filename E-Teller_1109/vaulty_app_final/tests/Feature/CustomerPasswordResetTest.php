<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario customer password reset test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\User;
use App\Services\ConfiguredOtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Lupa password nasabah: NIS -> kode OTP ke email terdaftar -> password baru.
 */
class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Irfan Cahyo',
            'username' => '17750',
            'nis' => '17750',
            'password' => bcrypt('PasswordLama123'),
            'role' => 'customer',
            'status' => 'active',
            'email' => 'irfancahyowibowo267@gmail.com',
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_customer_resets_password_with_email_otp(): void
    {
        $userId = $this->createCustomer();
        $user = User::findOrFail($userId);
        $user->createToken('perangkat-lama');

        $code = null;
        $this->mock(ConfiguredOtpMailer::class, function (MockInterface $mock) use (&$code): void {
            $mock->shouldReceive('sendCustomerPasswordResetCode')
                ->once()
                ->andReturnUsing(function (string $recipient, string $name, string $resetCode) use (&$code): void {
                    $this->assertSame('irfancahyowibowo267@gmail.com', $recipient);
                    $code = $resetCode;
                });
        });

        $this->postJson('/api/v1/auth/forgot-password', ['username' => '17750'])
            ->assertStatus(202)
            ->assertJsonPath('code', 'PASSWORD_RESET_CODE_SENT')
            ->assertJsonPath('email', fn ($email) => is_string($email) && str_contains($email, '*'));

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        $this->postJson('/api/v1/auth/reset-password', [
            'username' => '17750',
            'reset_code' => $code,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
            ->assertOk()
            ->assertJsonPath('code', 'PASSWORD_RESET_SUCCESS');

        $this->assertTrue(Hash::check('PasswordBaru123', $user->fresh()->password));

        // Semua token perangkat lama dicabut setelah password berubah.
        $this->assertSame(0, $user->tokens()->count());

        $this->assertNotNull(
            DB::table('customer_password_reset_tokens')->where('user_id', $userId)->value('used_at')
        );

        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $userId,
            'title' => 'Password berhasil diubah',
        ]);
    }

    public function test_wrong_otp_cannot_reset_password(): void
    {
        $userId = $this->createCustomer();

        DB::table('customer_password_reset_tokens')->insert([
            'user_id' => $userId,
            'token_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'username' => '17750',
            'reset_code' => '654321',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PASSWORD_RESET_CODE_INVALID');

        $this->assertTrue(
            Hash::check('PasswordLama123', User::findOrFail($userId)->password)
        );
    }
}
