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
}