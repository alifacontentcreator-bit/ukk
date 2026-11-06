<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman Data Petugas (Supervisor): daftar teller tanpa nomor WA.
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

    public function test_halaman_data_petugas_menampilkan_daftar_teller(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('supervisor.petugas'))
            ->assertOk()
            ->assertSee('Daftar Petugas')
            ->assertSee('Budi Teller')
            ->assertSee('Sari Teller')
            // Nomor WA tidak lagi ditampilkan di halaman ini.
            ->assertDontSee('No. WA')
            ->assertDontSee('Nomor WA')
            ->assertDontSee('whatsapp', false);
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
