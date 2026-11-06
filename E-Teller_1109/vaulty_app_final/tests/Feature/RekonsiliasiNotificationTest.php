<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifikasi pada halaman Rekonsiliasi Kas hanya ditampilkan sekali
 * (di bagian atas halaman oleh layout), tidak dobel di dalam konten.
 */
class RekonsiliasiNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $teller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::create([
            'name' => 'Bapak Supervisor',
            'username' => 'spv',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        $this->teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    public function test_pesan_saldo_awal_belum_ditetapkan_hanya_tampil_sekali(): void
    {
        // Saldo awal belum ditetapkan → penyerahan modal ditolak.
        $page = $this->actingAs($this->supervisor)
            ->from(route('supervisor.rekonsiliasi'))
            ->followingRedirects()
            ->post(route('supervisor.kas.serahkan-modal'), [
                'teller_id' => $this->teller->id,
                'amount' => '100000',
            ]);

        $page->assertOk();

        $pesan = 'Supervisor harus menetapkan saldo awal brankas terlebih dahulu.';

        $this->assertSame(1, substr_count($page->getContent(), $pesan));
    }

    public function test_pesan_sukses_hanya_tampil_sekali(): void
    {
        $page = $this->actingAs($this->supervisor)
            ->from(route('supervisor.rekonsiliasi'))
            ->followingRedirects()
            ->post(route('supervisor.kas.saldo-awal'), ['initial_balance' => '5000000']);

        $page->assertOk();

        $pesan = 'Saldo awal brankas berhasil disimpan.';

        $this->assertSame(1, substr_count($page->getContent(), $pesan));
    }
}
