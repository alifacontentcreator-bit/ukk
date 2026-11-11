<?php
/* Tujuan file: Migrasi ini mengubah struktur basis data untuk 2026 08 02 000000 create accounts table; perubahan dijalankan dan dibatalkan oleh mekanisme migrasi Laravel. */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            // Relasi ke data identitas nasabah (users.role = customer)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Format: <KODE_JURUSAN>-<ANGKATAN>-<KELAS_URUT>-<PRESENSI>, contoh: RPL-2024-01-01
            $table->string('account_number', 30)->unique();

            // PIN hash acak sampai nasabah menetapkan PIN pribadi di aplikasi.
            $table->string('pin');
            $table->boolean('pin_is_default')->default(true);

            // Saldo rekening. Saldo mengendap minimum (Rp10.000) divalidasi di level aplikasi.
            $table->decimal('balance', 15, 2)->default(0);

            // Jenis produk simpanan (kebutuhan wawancara): dipisah agar jurnal & laporan
            // bisa membedakan Tabungan Siswa vs Tabungan Guru/Karyawan.
            $table->enum('product_type', ['tabungan_siswa', 'tabungan_karyawan'])->default('tabungan_siswa');

            // Token unik untuk QR identitas nasabah (dipakai teller & aplikasi mobile nasabah)
            $table->string('qr_token', 80)->nullable()->unique();

            $table->string('status', 20)->default('active'); // active / blocked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
