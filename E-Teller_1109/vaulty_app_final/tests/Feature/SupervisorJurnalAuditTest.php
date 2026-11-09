<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Jurnal Audit Supervisor:
 * - hanya bisa dilihat (tidak ada aksi ubah/hapus),
 * - seluruh aktivitas transaksi tercatat, berhasil maupun gagal,
 * - setiap baris mencantumkan keterangan Berhasil/Gagal.
 */
class SupervisorJurnalAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $teller;

    private User $admin;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'SPV Jurnal']);
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Jurnal']);
        $this->teller = User::factory()->create(['role' => 'teller', 'name' => 'Teller Jurnal']);

        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Nasabah Jurnal']);

        $this->account = Account::create([
            'user_id' => $customer->id,
            'account_number' => 'JRN-2024-01-01',
            'pin' => bcrypt('123456'),
            'pin_is_default' => false,
            'balance' => 50000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);
    }

    private function transaksiBerhasil(string $ref = 'REFJRN0001'): Transaction
    {
        return DB::transaction(fn () => app(TransactionPostingService::class)->post(
            $this->account->fresh(),
            $this->teller,
            'deposit',
            25000,
            'Setoran uji jurnal',
            $ref
        ));
    }

    public function test_jurnal_menampilkan_transaksi_dengan_keterangan_berhasil(): void
    {
        $trx = $this->transaksiBerhasil();

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal'))
            ->assertOk()
            ->assertSee('Jurnal Transaksi')
            ->assertSee($trx->reference_number)
            ->assertSee('Nasabah Jurnal')
            ->assertSee('Teller Jurnal')
            ->assertSee('Berhasil');
    }

    public function test_transaksi_berhasil_mencatat_aktivitas_berhasil(): void
    {
        $this->transaksiBerhasil();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaksi.berhasil',
            'module' => 'transaksi',
            'status' => 'success',
            'user_id' => $this->teller->id,
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal'))
            ->assertOk()
            ->assertSee('Aktivitas Transaksi (Termasuk yang Gagal)')
            ->assertSee('Transaksi berhasil');
    }

    public function test_percobaan_transaksi_gagal_tercatat_beserta_alasannya(): void
    {
        // Penarikan melebihi batas saldo mengendap → DomainException.
        $this->actingAs($this->teller)
            ->post(route('teller.transaksi.store'), [
                'account_number' => $this->account->account_number,
                'type' => 'withdrawal',
                'amount' => 50000,
                'description' => 'Tarik uji gagal',
            ])
            ->assertSessionHasErrors('error');

        $log = AuditLog::where('action', 'transaksi.gagal')->first();

        $this->assertNotNull($log, 'Percobaan transaksi gagal harus tercatat di audit_logs.');
        $this->assertSame('failed', $log->status);
        $this->assertSame($this->teller->id, $log->user_id);
        $this->assertStringContainsString('Penarikan', $log->description);
        $this->assertStringContainsString('Minimal saldo mengendap', $log->description);

        // Tidak ada transaksi tersimpan, tetapi aktivitasnya muncul di jurnal SPV.
        $this->assertDatabaseCount('transactions', 0);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal'))
            ->assertOk()
            ->assertSee('Transaksi gagal')
            ->assertSee('Minimal saldo mengendap')
            ->assertSee('Gagal');
    }

    public function test_transaksi_ditolak_tampil_sebagai_gagal(): void
    {
        Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-JRN-GAGAL',
            'reference_number' => 'REFJRN-GAGAL',
            'type' => 'withdrawal',
            'amount' => 15000,
            'status' => 'rejected',
            'rejected_reason' => 'Ditolak Supervisor karena bukti tidak lengkap.',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal'))
            ->assertOk()
            ->assertSee('REFJRN-GAGAL')
            ->assertSee('Ditolak Supervisor karena bukti tidak lengkap.')
            ->assertSee('Gagal');
    }

    public function test_filter_keterangan_berhasil_dan_gagal_berfungsi(): void
    {
        $this->transaksiBerhasil('REFJRN-FILTER-OK');

        Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-JRN-FILTER-GAGAL',
            'reference_number' => 'REFJRN-FILTER-GAGAL',
            'type' => 'deposit',
            'amount' => 5000,
            'status' => 'rejected',
        ]);

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal', ['status' => 'berhasil']))
            ->assertOk()
            ->assertSee('REFJRN-FILTER-OK')
            ->assertDontSee('REFJRN-FILTER-GAGAL');

        $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal', ['status' => 'gagal']))
            ->assertOk()
            ->assertSee('REFJRN-FILTER-GAGAL')
            ->assertDontSee('REFJRN-FILTER-OK');
    }

    public function test_jurnal_tidak_punya_aksi_ubah_atau_hapus(): void
    {
        $this->transaksiBerhasil();

        $html = $this->actingAs($this->supervisor)
            ->get(route('supervisor.jurnal'))
            ->assertOk()
            ->assertSee('hanya dapat dilihat')
            ->assertDontSee('Hapus transaksi')
            ->assertDontSee('Edit transaksi')
            ->getContent();

        // Form apa pun yang menyentuh data transaksi/jurnal hanya boleh GET.
        preg_match_all('/<form\b[^>]*>/i', $html, $forms);

        foreach ($forms[0] as $form) {
            $menyentuhData = str_contains($form, 'supervisor/jurnal') || str_contains($form, 'transaksi');

            if ($menyentuhData) {
                $this->assertMatchesRegularExpression(
                    '/method="GET"/i',
                    $form,
                    'Form pada Jurnal Audit hanya boleh GET (tidak boleh ubah data): ' . $form
                );
            }
        }

        // Tidak ada route destructive (DELETE/PUT/PATCH) untuk jurnal transaksi.
        $destruktif = collect(Route::getRoutes()->getRoutes())->filter(function ($route) {
            $uri = $route->uri();

            $menyentuhTransaksi = str_contains($uri, 'jurnal')
                || str_contains($uri, 'transaksi')
                || str_contains($uri, 'transaction');

            return $menyentuhTransaksi
                && (bool) array_intersect(['DELETE', 'PUT', 'PATCH'], $route->methods());
        });

        $this->assertCount(
            0,
            $destruktif,
            'Tidak boleh ada route ubah/hapus transaksi: ' . $destruktif->pluck('uri')->implode(', ')
        );
    }

    public function test_url_hapus_atau_ubah_jurnal_ditolak(): void
    {
        $this->actingAs($this->supervisor)
            ->delete(route('supervisor.jurnal'))
            ->assertStatus(405);

        $this->actingAs($this->supervisor)
            ->put(route('supervisor.jurnal'))
            ->assertStatus(405);
    }

    public function test_teller_tidak_bisa_membuka_jurnal_supervisor(): void
    {
        $this->actingAs($this->teller)
            ->get(route('supervisor.jurnal'))
            ->assertForbidden();
    }
}
