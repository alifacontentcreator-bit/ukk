<?php
/* Tujuan file: Perintah Artisan ini menjalankan tugas administrasi nonaktifkan akun alumni yang dapat dipanggil melalui CLI. */


namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Menonaktifkan otomatis akun nasabah SISWA yang sudah menjadi alumni.
 *
 * Akun TIDAK dihapus dan riwayat transaksi TIDAK disentuh sama sekali, supaya
 * Jurnal Audit Supervisor tetap memuat riwayat transaksi dari dulu sampai
 * sekarang.
 *
 * Aturan: siswa masuk tahun X (kolom users.angkatan) dianggap alumni pada
 * 31 Desember tahun (X + 3). Contoh: angkatan 2024 -> nonaktif pada
 * 31 Desember 2027.
 * 
 * Cara pakai manual:
 *   php artisan akun:nonaktifkan-alumni --dry-run   (hanya melihat daftar, tanpa mengubah status)
 *   php artisan akun:nonaktifkan-alumni             (nonaktifkan seluruh alumni)
 */
class NonaktifkanAkunAlumni extends Command
{
    protected $signature = 'akun:nonaktifkan-alumni
                            {--dry-run : Hanya tampilkan daftar akun, tanpa mengubah status}';

    protected $description = 'Nonaktifkan akun siswa yang sudah menjadi alumni (angkatan + 3 tahun, per 31 Desember) tanpa menghapus data';

    /** Lama sekolah SMK (3tahun). */
    private const LAMA_STUDI_TAHUN = 3;

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $hariIni = now()->startOfDay();

        // Ambil siswa yang angkatannya sudah cukup lama, lalu cek tanggal pastinya (31 Desember).
        $alumni = User::query()
            ->with('account')
            ->where('role', 'customer')
            ->whereNotNull('nis')
            ->whereNotNull('angkatan')
            ->where('angkatan', '<=', $hariIni->year - self::LAMA_STUDI_TAHUN)
            ->get()
            ->filter(function (User $user) use ($hariIni) {
                $tanggalLulus = Carbon::create($user->angkatan + self::LAMA_STUDI_TAHUN, 12, 31)->startOfDay();
                return $hariIni->gte($tanggalLulus);
            });

        if ($alumni->isEmpty()) {
            $this->info('Tidak ada akun alumni yang perlu dinonaktifkan.');
            return self::SUCCESS;
        }

        $dinonaktifkan = 0;
        $sudahNonaktif = 0;
        $gagal         = 0;

        foreach ($alumni as $user) {
            $label = "{$user->name} (NIS {$user->nis}, angkatan {$user->angkatan})";

            if ($user->status !== 'active') {
                $sudahNonaktif++;
                $this->line("SUDAH NONAKTIF  {$label}");
                continue;
            }

            if ($dryRun) {
                $dinonaktifkan++;
                $this->line("AKAN DINONAKTIFKAN  {$label}");
                continue;
            }

            try {
                /*
                 * Hanya status akun yang diubah. Rekening, saldo, notifikasi, dan
                 * SELURUH riwayat transaksi tetap utuh sehingga laporan Supervisor
                 * tidak kehilangan data.
                 */
                $user->forceFill(['status' => 'blocked'])->save();

                $dinonaktifkan++;
                $this->info("DINONAKTIFKAN  {$label}");
                Log::info("Akun alumni dinonaktifkan otomatis (riwayat transaksi tetap tersimpan): {$label}");
            } catch (\Throwable $e) {
                $gagal++;
                $this->error("GAGAL  {$label} - {$e->getMessage()}");
                Log::error("Gagal menonaktifkan akun alumni {$label}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Selesai. Dinonaktifkan: {$dinonaktifkan}, sudah nonaktif: {$sudahNonaktif}, gagal: {$gagal}.");
        $this->line('Akun tidak dihapus dan riwayat transaksi tetap tersimpan.');

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
