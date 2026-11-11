<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario admin pin reset page test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\CredentialResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Halaman Admin > Reset PIN menampilkan pengajuan lupa PIN dari nasabah
 * beserta tombol Setujui/Tolak.
 */
class AdminPinResetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_pending_pin_reset_requests_with_approval_buttons(): void
    {
        $admin = User::create([
            'name' => 'Admin Sekolah',
            'username' => 'admin-page',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $customer = User::create([
            'name' => 'Irfan Cahyo',
            'username' => '17750',
            'nis' => '17750',
            'email' => 'irfan@example.com',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $accountId = DB::table('accounts')->insertGetId([
            'user_id' => $customer->id,
            'account_number' => 'TKJ-2026-01-02',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'pin_attempts' => 3,
            'pin_reset_required' => true,
            'pin_blocked' => true,
            'balance' => 0,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('credential_reset_requests')->insert([
            'account_id' => $accountId,
            'user_id' => $customer->id,
            'type' => CredentialResetRequest::TYPE_PIN,
            'requested_by' => $customer->id,
            'reason' => 'Nasabah lupa PIN dan mengajukan reset melalui aplikasi.',
            'verification_note' => 'Diajukan sendiri oleh nasabah melalui aplikasi.',
            'status' => CredentialResetRequest::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reset-pin'))
            ->assertOk()
            ->assertSee('Pengajuan Reset PIN')
            ->assertSee('Irfan Cahyo')
            ->assertSee('17750')
            ->assertSee('Akun diblokir (PIN salah 3x)')
            ->assertSee('Setujui')
            ->assertSee('Tolak');

        // Daftar pengguna juga menampilkan status blokir PIN, bukan "Aktif".
        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Blokir (PIN)')
            ->assertSee('Reset PIN');
    }

    public function test_teller_reset_pin_page_is_removed(): void
    {
        $teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'teller-page',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $this->actingAs($teller)
            ->get('/teller/reset-pin')
            ->assertNotFound();
    }
}
