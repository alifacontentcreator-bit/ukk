<?php
/* Tujuan file: Pengujian ini memeriksa perilaku aplikasi pada skenario admin student import test agar alur terkait tetap berjalan sesuai harapan. */


namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStudentImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['audit_logs', 'accounts', 'users', 'system_settings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status')->default('active');
            $table->string('email')->nullable()->unique();
            $table->string('nis')->nullable()->unique();
            $table->string('kelas')->nullable();
            $table->string('jurusan')->nullable();
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->string('kelas_urut')->nullable();
            $table->unsignedSmallInteger('angkatan')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('activation_deadline')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('account_number')->unique();
            $table->string('pin');
            $table->boolean('pin_is_default')->default(true);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('product_type');
            $table->string('qr_token')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->string('method')->nullable();
            $table->string('route')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('status')->default('success');
            $table->timestamps();
        });
    }

    public function test_excel_import_creates_an_unactivated_student_and_first_account_number(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'siswa.csv',
            "Nama,NIS,Kelas,Email\nAlya Putri,54321,X RPL1,alya@example.test\n"
        );

        $this->actingAs($admin)
            ->post(route('admin.users.import-siswa'), ['file' => $file])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('success', '1 akun siswa dibuat dan menunggu aktivasi.');

        $student = User::where('nis', '54321')->firstOrFail();

        // Nasabah baru wajib NONAKTIF sampai aktivasi di aplikasi mobile selesai.
        $this->assertTrue($student->must_change_password);
        $this->assertSame('inactive', $student->status);
        $this->assertTrue($student->isAwaitingActivation());
        $this->assertNotNull($student->activation_deadline);
        $this->assertTrue(
            $student->activation_deadline->between(
                now()->addDays(User::ACTIVATION_WINDOW_DAYS - 1),
                now()->addDays(User::ACTIVATION_WINDOW_DAYS + 1)
            )
        );

        $this->assertDatabaseHas('accounts', [
            'user_id' => $student->id,
            'account_number' => 'RPL-' . now()->year . '-01-01',
            'balance' => 0,
            'pin_is_default' => true,
        ]);
        $this->assertSame(1, DB::table('accounts')->where('user_id', $student->id)->count());
    }
}