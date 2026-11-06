<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Data Petugas (Supervisor): tanpa filter nomor WA.
 */
class SupervisorPetugasPageTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::create([
            'name' => 'Bapak Supervisor',
            'username' => 'spv',
            'password' => 'password',
            'role' => 'supervisor',
        ]);

        User::create([
            'name' => 'Budi Teller',
            'username' => 'budi',
            'kelas' => 'X TKJ 1',
            'no_telp' => '081234567890',
            'password' => 'password',
            'role' => 'teller',
        ]);

        User::create([
            'name' => 'Sari Teller',
            'username' => 'sari',
            'kelas' => 'XI AKL 2',
            'password' => 'password',
            'role' => 'teller',
        ]);
    }

    public function test_halaman_data_petugas_tanpa_filter_nomor_wa(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.petugas'))
            ->assertOk()
            ->assertSee('Daftar Petugas')
            ->assertSee('Budi Teller')
            ->assertSee('Sari Teller')
            ->assertSee('081234567890')
            // Filter nomor WA sudah dihapus.
            ->assertDontSee('name="wa"', false)
            ->assertDontSee('Nomor WA');
    }

    public function test_pencarian_nama_tetap_berfungsi(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.petugas', ['q' => 'Sari']))
            ->assertOk()
            ->assertSee('Sari Teller')
            ->assertDontSee('Budi Teller');
    }

    public function test_parameter_wa_lama_diabaikan(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.petugas', ['wa' => 'belum']))
            ->assertOk()
            ->assertSee('Budi Teller')
            ->assertSee('Sari Teller');
    }
}
