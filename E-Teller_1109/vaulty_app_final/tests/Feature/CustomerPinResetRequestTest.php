<?php

namespace Tests\Feature;

use App\Models\CredentialResetRequest;
use App\Models\User;
use App\Services\PinResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lupa PIN: nasabah memasukkan NIS lalu mengajukan; Admin menyetujui/menolak
 * dari halaman Reset PIN. Aplikasi membaca hasilnya lewat endpoint status.
 */
class CustomerPinResetRequestTest extends TestCase
{
    use RefreshDatabase;

    private int $customerId;
    private int $accountId;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerId = DB::table('users')->insertGetId([
            'name' => 'Irfan Cahyo',
            'username' => '17750',
            'nis' => '17750',
            'password' => bcrypt('Password123'),
            'role' => 'customer',
            'status' => 'active',
            'email' => 'irfan@example.com',
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminId = DB::table('users')->insertGetId([
            'name' => 'Admin Sekolah',
            'username' => 'admin01',
            'password' => bcrypt('Password123'),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->accountId = DB::table('accounts')->insertGetId([
            'user_id' => $this->customerId,
            'account_number' => 'TKJ-2026-01-02',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'pin_attempts' => 0,
            'pin_reset_required' => false,
            'balance' => 0,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function customer(): User
    {
        return User::findOrFail($this->customerId);
    }

    public function test_customer_requests_pin_reset_and_admin_approves(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', ['nis' => '17750'])
            ->assertCreated()
            ->assertJsonPath('code', 'PIN_RESET_REQUEST_SENT');

        $this->assertDatabaseHas('credential_reset_requests', [
            'account_id' => $this->accountId,
            'requested_by' => $this->customerId,
            'type' => 'pin',
            'status' => 'pending',
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/pin/reset-request/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.pin_setup_required', false);

        $pinRequest = CredentialResetRequest::firstOrFail();
        $approved = app(PinResetService::class)
            ->approve($pinRequest, User::findOrFail($this->adminId));

        $this->assertTrue($approved);
        $this->assertDatabaseHas('accounts', [
            'id' => $this->accountId,
            'pin_is_default' => true,
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/pin/reset-request/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.pin_setup_required', true);
    }

    public function test_admin_rejection_is_visible_to_customer_and_account_stays_blocked(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update([
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'pin_blocked' => true,
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', [
                'nis' => '17750',
                'reason' => 'PIN salah 3 kali.',
            ])
            ->assertCreated();

        $pinRequest = CredentialResetRequest::firstOrFail();
        app(PinResetService::class)->reject(
            $pinRequest,
            User::findOrFail($this->adminId),
            'Data diri tidak sesuai.'
        );

        // Penolakan TIDAK membuka blokir: akun tetap diblokir.
        $this->assertDatabaseHas('accounts', [
            'id' => $this->accountId,
            'pin_blocked' => true,
            'pin_reset_required' => true,
            'pin_attempts' => 3,
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('account.pin_blocked', true)
            ->assertJsonPath('account.pin_reset_required', true);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/pin/reset-request/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.review_note', 'Data diri tidak sesuai.')
            ->assertJsonPath('data.pin_blocked', true);

        // Nasabah tetap bisa mengajukan ulang setelah ditolak.
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', [
                'nis' => '17750',
                'reason' => 'Data sudah diperbaiki, mohon buka blokir.',
            ])
            ->assertCreated();
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', ['nis' => '17750'])
            ->assertCreated();

        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', ['nis' => '17750'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PIN_RESET_PENDING');

        $this->assertSame(1, CredentialResetRequest::count());
    }

    public function test_blocked_account_must_give_reason_and_approval_unblocks_it(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update([
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'pin_blocked' => true,
            'status' => 'blocked',
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/pin/reset-request/status')
            ->assertOk()
            ->assertJsonPath('data.pin_blocked', true);

        // Tanpa alasan, pengajuan buka blokir ditolak.
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', ['nis' => '17750'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'REASON_REQUIRED');

        $reason = 'PIN salah 3 kali karena lupa, mohon buka blokir.';

        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', [
                'nis' => '17750',
                'reason' => $reason,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('credential_reset_requests', [
            'account_id' => $this->accountId,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        $pinRequest = CredentialResetRequest::firstOrFail();
        $approved = app(PinResetService::class)
            ->approve($pinRequest, User::findOrFail($this->adminId));

        $this->assertTrue($approved);
        $this->assertDatabaseHas('accounts', [
            'id' => $this->accountId,
            'pin_blocked' => false,
            'pin_reset_required' => false,
            'pin_is_default' => true,
            'pin_attempts' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/pin/reset-request/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.pin_blocked', false)
            ->assertJsonPath('data.pin_setup_required', true);
    }

    public function test_blocked_pin_customer_can_still_login_and_request_unblock(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update([
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'pin_blocked' => true,
            'status' => 'blocked',
        ]);

        // Login tetap diizinkan agar nasabah bisa mengajukan buka blokir.
        $this->postJson('/api/v1/auth/login', [
            'username' => '17750',
            'password' => 'Password123',
            'device_name' => 'uji-blokir',
        ])
            ->assertOk()
            ->assertJsonPath('account.pin_blocked', true)
            ->assertJsonPath('account.status', 'blocked');

        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', [
                'nis' => '17750',
                'reason' => 'PIN salah 3 kali, mohon buka blokir.',
            ])
            ->assertCreated();
    }

    public function test_wrong_nis_is_rejected_without_creating_request(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/pin/reset-request', ['nis' => '99999'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'NIS_NOT_MATCH');

        $this->assertSame(0, CredentialResetRequest::count());
    }
}
