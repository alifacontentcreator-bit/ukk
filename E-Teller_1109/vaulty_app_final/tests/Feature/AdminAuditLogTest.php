<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Admin → Log & Audit harus memuat riwayat transaksi berhasil maupun gagal.
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Audit']);
        $this->teller = User::factory()->create(['role' => 'teller', 'name' => 'Teller Audit']);
    }

    private function buatAktivitasTransaksi(): void
    {
        TransactionActivityLogger::berhasil($this->teller->id, [
            'type' => 'deposit',
            'amount' => 50000,
            'account_number' => 'AUD-2024-01-01',
            'reference_number' => 'REFAUDITOK',
            'nasabah' => 'Nasabah Audit',
        ], 'Setoran berhasil diposting.');

        TransactionActivityLogger::gagal($this->teller->id, [
            'type' => 'withdrawal',
            'amount' => 50000,
            'account_number' => 'AUD-2024-01-01',
            'reference_number' => 'REFAUDITGAGAL',
            'nasabah' => 'Nasabah Audit',
        ], 'Minimal saldo mengendap Rp 10.000 tidak terpenuhi.');

        TransactionActivityLogger::diajukan($this->teller->id, [
            'type' => 'deposit',
            'amount' => 25000,
            'account_number' => 'AUD-2024-01-01',
            'reference_number' => 'REFAUDITMENUNGGU',
            'nasabah' => 'Nasabah Audit',
        ], 'Permintaan menunggu konfirmasi PIN nasabah.');
    }

    public function test_admin_melihat_riwayat_transaksi_berhasil_dan_gagal(): void
    {
        $this->buatAktivitasTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertSee('Riwayat Transaksi (Berhasil &amp; Gagal)', false)
            ->assertSee('Transaksi berhasil')
            ->assertSee('Transaksi gagal')
            ->assertSee('REFAUDITOK')
            ->assertSee('REFAUDITGAGAL')
            ->assertSee('Setoran berhasil diposting.')
            ->assertSee('Minimal saldo mengendap Rp 10.000 tidak terpenuhi.')
            ->assertSee('Teller Audit')
            ->assertSee('Berhasil')
            ->assertSee('Gagal');
    }

    public function test_ringkasan_menghitung_transaksi_berhasil_gagal_dan_menunggu(): void
    {
        $this->buatAktivitasTransaksi();

        $ringkasan = $this->actingAs($this->admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->viewData('ringkasan');

        $this->assertSame(1, $ringkasan['trx_berhasil']);
        $this->assertSame(1, $ringkasan['trx_gagal']);
        $this->assertSame(1, $ringkasan['trx_menunggu']);
    }

    public function test_filter_transaksi_gagal_hanya_menampilkan_yang_gagal(): void
    {
        $this->buatAktivitasTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log', ['jenis' => 'transaksi_gagal']))
            ->assertOk()
            ->assertSee('REFAUDITGAGAL')
            ->assertDontSee('REFAUDITOK')
            ->assertDontSee('REFAUDITMENUNGGU');
    }

    public function test_filter_transaksi_berhasil_hanya_menampilkan_yang_berhasil(): void
    {
        $this->buatAktivitasTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log', ['jenis' => 'transaksi_berhasil']))
            ->assertOk()
            ->assertSee('REFAUDITOK')
            ->assertDontSee('REFAUDITGAGAL');
    }

    public function test_pencarian_keterangan_transaksi_berfungsi(): void
    {
        $this->buatAktivitasTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log', ['q' => 'saldo mengendap']))
            ->assertOk()
            ->assertSee('REFAUDITGAGAL')
            ->assertDontSee('REFAUDITOK');
    }

    public function test_aktivitas_sistem_lain_tetap_tampil(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'module' => 'users',
            'description' => 'Data pengguna diubah oleh administrator.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertSee('Riwayat Aktivitas Sistem')
            ->assertSee('Data pengguna diubah oleh administrator.');
    }

    public function test_halaman_audit_hanya_bisa_dilihat(): void
    {
        $this->buatAktivitasTransaksi();

        $html = $this->actingAs($this->admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertSee('hanya dapat dilihat')
            ->assertDontSee('Hapus log')
            ->getContent();

        preg_match_all('/<form\b[^>]*>/i', $html, $forms);
        foreach ($forms[0] as $form) {
            if (str_contains($form, 'audit-log')) {
                $this->assertMatchesRegularExpression('/method="GET"/i', $form, 'Form audit hanya boleh GET: ' . $form);
            }
        }

        $destruktif = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            return str_contains($route->uri(), 'audit-log')
                && (bool) array_intersect(['DELETE', 'PUT', 'PATCH', 'POST'], $route->methods());
        });

        $this->assertCount(0, $destruktif, 'Tidak boleh ada route ubah/hapus audit log.');
    }

    public function test_teller_tidak_bisa_membuka_log_audit(): void
    {
        $this->actingAs($this->teller)
            ->get(route('admin.audit-log'))
            ->assertForbidden();
    }

    // ─────────── Arsip transaksi (termasuk data lama) ───────────

    private function buatArsipTransaksi(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Nasabah Arsip']);

        $account = Account::create([
            'user_id' => $customer->id,
            'account_number' => 'ARS-2024-01-01',
            'pin' => bcrypt('123456'),
            'pin_is_default' => false,
            'balance' => 50000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-ARS-OK',
            'reference_number' => 'REFARSIPOK',
            'type' => 'deposit',
            'amount' => 25000,
            'status' => 'success',
        ]);

        Transaction::create([
            'account_id' => $account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-ARS-GAGAL',
            'reference_number' => 'REFARSIPGAGAL',
            'type' => 'withdrawal',
            'amount' => 15000,
            'status' => 'rejected',
            'rejected_reason' => 'Ditolak karena bukti tidak lengkap.',
        ]);
    }

    public function test_arsip_transaksi_lama_tetap_tampil_dengan_keterangan(): void
    {
        $this->buatArsipTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertSee('Transaksi Tercatat')
            ->assertSee('REFARSIPOK')
            ->assertSee('REFARSIPGAGAL')
            ->assertSee('Nasabah Arsip')
            ->assertSee('Ditolak karena bukti tidak lengkap.')
            ->assertSee('Berhasil')
            ->assertSee('Gagal');
    }

    public function test_filter_transaksi_gagal_juga_menyaring_arsip_transaksi(): void
    {
        $this->buatArsipTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log', ['jenis' => 'transaksi_gagal']))
            ->assertOk()
            ->assertSee('REFARSIPGAGAL')
            ->assertDontSee('REFARSIPOK');
    }

    public function test_filter_aktivitas_sistem_menyembunyikan_bagian_transaksi(): void
    {
        $this->buatArsipTransaksi();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-log', ['jenis' => 'sistem']))
            ->assertOk()
            ->assertSee('Riwayat Aktivitas Sistem')
            ->assertDontSee('Transaksi Tercatat')
            ->assertDontSee('REFARSIPOK');
    }
}
