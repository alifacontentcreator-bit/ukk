<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserIdentityValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('email')->nullable()->unique();
            $table->string('nis', 30)->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('alamat')->nullable();
            $table->string('kelas', 30)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('photo')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function test_duplicate_email_shows_a_clear_message_when_adding_a_customer(): void
    {
        $admin = $this->createAdmin();
        User::create([
            'name' => 'Existing customer',
            'username' => 'existing-customer',
            'password' => 'password',
            'role' => 'customer',
            'email' => 'used@example.test',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->customerData([
                'email' => 'used@example.test',
            ]))
            ->assertSessionHasErrors([
                'email' => 'Email ini sudah digunakan oleh pengguna lain.',
            ]);
    }

    public function test_student_identity_must_be_five_digits_and_unique(): void
    {
        $admin = $this->createAdmin();
        User::create([
            'name' => 'Existing customer',
            'username' => '12345',
            'password' => 'password',
            'role' => 'customer',
            'email' => 'existing@example.test',
            'nis' => '12345',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->customerData([
                'nis' => '12345',
            ]))
            ->assertSessionHasErrors([
                'nis' => 'Nomor induk siswa ini sudah digunakan oleh pengguna lain.',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->customerData([
                'email' => 'another@example.test',
                'nis' => '1234',
            ]))
            ->assertSessionHasErrors([
                'nis' => 'Nomor induk siswa wajib terdiri dari tepat 5 angka.',
            ]);
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }

    private function customerData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New customer',
            'role' => 'customer',
            'email' => 'new@example.test',
            'tipe_nasabah' => 'siswa',
            'nis' => '54321',
            'kode_kelas' => 'X RPL1',
        ], $overrides);
    }
}
