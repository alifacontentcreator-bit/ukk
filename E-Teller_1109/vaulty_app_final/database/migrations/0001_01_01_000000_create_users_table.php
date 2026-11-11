<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 0001 01 01 000000 create users table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique(); // username untuk login
            $table->string('password');
            $table->enum('role', ['admin', 'supervisor', 'teller', 'customer']);

            // Kolom khusus role customer - siswa (nasabah pelajar)
            $table->string('nis', 30)->nullable();               // Nomor Induk Siswa (jadi username)
            $table->string('nisn', 30)->nullable();              // Nomor Induk Siswa Nasional
            $table->string('kelas', 30)->nullable();              // Label lengkap, mis. "XII RPL 1"
            $table->string('jurusan', 10)->nullable();            // Kode jurusan: RPL, TKJ, DKV, AK, LPS, MP, BR, BD
            $table->unsignedTinyInteger('tingkat')->nullable();   // 10 (X), 11 (XI), 12 (XII)
            $table->string('kelas_urut', 2)->nullable();          // Urutan kelas dlm jurusan yg sama (01, 02, ...)
            $table->unsignedSmallInteger('angkatan')->nullable(); // Tahun angkatan, mis. 2024

            // Kolom khusus role customer - guru/karyawan (nasabah non-siswa)
            $table->string('nip', 30)->nullable();                // NIP/NIK, jadi username
            $table->enum('jabatan', ['GRU', 'KAR'])->nullable();  // GRU = Guru, KAR = Karyawan/Staf

            $table->string('photo')->nullable();
            $table->string('status', 20)->default('active'); // active / blocked
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
