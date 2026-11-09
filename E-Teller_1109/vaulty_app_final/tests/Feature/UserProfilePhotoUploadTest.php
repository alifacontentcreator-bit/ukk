<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfilePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_user_profile_photo_when_editing_a_user(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Nasabah',
            'username' => 'nasabah',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'email' => 'nasabah@example.test',
                'photo' => UploadedFile::fake()->create('profile.jpg', 100, 'image/jpeg'),
            ])
            ->assertRedirect(route('admin.users'));

        $user->refresh();

        $this->assertNotEmpty($user->photo);
        Storage::disk('public')->assertExists($user->photo);
    }

    /**
     * Foto lama harus dibuang dari disk saat diganti, supaya tidak ada
     * berkas yatim dan foto yang sudah tidak dipakai tetap bisa diakses.
     */
    public function test_uploading_a_new_photo_removes_the_previous_file(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Nasabah',
            'username' => 'nasabah',
            'password' => 'password',
            'role' => 'customer',
            'photo' => 'photos/foto-lama.jpg',
        ]);

        Storage::disk('public')->put('photos/foto-lama.jpg', 'foto lama');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'email' => 'nasabah@example.test',
                'photo' => UploadedFile::fake()->image('foto-baru.jpg', 200, 200),
            ])
            ->assertRedirect(route('admin.users'));

        $user->refresh();

        $this->assertNotSame('photos/foto-lama.jpg', $user->photo);
        Storage::disk('public')->assertMissing('photos/foto-lama.jpg');
        Storage::disk('public')->assertExists($user->photo);
    }

    /**
     * Kolom "Sandi baru (opsional)" sudah dihapus dari formulir ubah pengguna.
     * Pengaturan sandi pegawai dilakukan lewat menu Atur Ulang Sandi.
     */
    public function test_edit_form_no_longer_shows_the_optional_password_field(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Nasabah',
            'username' => 'nasabah',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertSee('Foto profil', false)
            ->assertDontSee('Sandi baru (opsional)', false)
            ->assertDontSee('name="password"', false);
    }

    /**
     * Bila berkas foto sudah dipilih di formulir tetapi tidak sampai ke server
     * (mis. melebihi batas unggah PHP), penyimpanan harus ditolak dengan pesan
     * yang jelas — bukan "berhasil" padahal foto tidak berganti.
     */
    public function test_update_is_rejected_when_the_chosen_photo_never_reached_the_server(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $user = User::create([
            'name' => 'Nasabah',
            'username' => 'nasabah',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $user))
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'email' => 'nasabah@example.test',
                'photo_expected' => '1',
            ])
            ->assertRedirect(route('admin.users.edit', $user))
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->photo);
    }


}