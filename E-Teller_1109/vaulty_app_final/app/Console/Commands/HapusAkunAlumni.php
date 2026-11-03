<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus otomatis akun nasabah SISWA yang sudah menjadi alumni.
 *
 * Aturan: siswa masuk tahun X (kolom users.angkatan) dianggap alumni
 * pada 31 Desember tahun (X + 3). Contoh: angkatan 2024 -> akun dihapus
 * pada 31 Desember 2027.
 *
 * Cara pakai manual:
 *   php artisan akun:hapus-alumni --dry-run          (hanya melihat daftar, tidak menghapus)
 *   php artisan akun:hapus-alumni                    (hapus alumni yang saldonya 0)
 *   php artisan akun:hapus-alumni --termasuk-saldo   (hapus juga yang saldonya masih ada)
 */
class HapusAkunAlumni extends Command
{
    protected $signature = 'akun:hapus-alumni
                            {--dry-run : Hanya tampilkan daftar akun, tanpa menghapus}
                            {--termasuk-saldo : Hapus juga akun alumni yang saldonya masih lebih dari 0}';

    protected $description = 'Hapus otomatis akun siswa yang sudah menjadi alumni (angkatan + 3 tahun, per 31 Desember)';

    /** Lama sekolah SMK (tahun). */
    private const LAMA_STUDI_TAHUN = 3;

    public function handle(): int
    {
        $dryRun        = (bool) $this->option('dry-run');
        $termasukSaldo = (bool) $this->option('termasuk-saldo');
        $hariIni       = now()->startOfDay();

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
            $this->info('Tidak ada akun alumni yang perlu dihapus.');
            return self::SUCCESS;
        }

        $dihapus = 0;
        $dilewati = 0;
        $gagal = 0;

        foreach ($alumni as $user) {
            $saldo = (float) ($user->account->balance ?? 0);
            $label = "{$user->name} (NIS {$user->nis}, angkatan {$user->angkatan})";

            if ($saldo > 0 && !$termasukSaldo) {
                $dilewati++;
                $this->warn("DILEWATI  {$label} - saldo masih Rp " . number_format($saldo, 0, ',', '.'));
                continue;
            }

            if ($dryRun) {
                $dihapus++;
                $this->line("AKAN DIHAPUS  {$label}");
                continue;
            }

            try {
                DB::transaction(function () use ($user) {
                    $accountIds = DB::table('accounts')->where('user_id', $user->id)->pluck('id');

                    // Transaksi memakai FK RESTRICT ke rekening, jadi harus dihapus lebih dulu.
                    // accounting_journals ikut terhapus (cascade), notifikasi otomatis dilepas.
                    DB::table('transactions')
                        ->whereIn('account_id', $accountIds)
                        ->orderBy('id')
                        ->pluck('id')
                        ->chunk(500)
                        ->each(function ($chunk) {
                            DB::table('transaction_corrections')->whereIn('transaction_id', $chunk)->delete();
                            DB::table('transactions')->whereIn('id', $chunk)->delete();
                        });

                    // Rekening, notifikasi, dan permintaan reset sandi ikut terhapus (cascade).
                    $user->delete();
                });

                if ($user->photo) {
                    Storage::disk('public')->delete($user->photo);
                }

                $dihapus++;
                $this->info("DIHAPUS  {$label}");
                Log::info("Akun alumni dihapus otomatis: {$label}");
            } catch (\Throwable $e) {
                $gagal++;
                $this->error("GAGAL  {$label} - {$e->getMessage()}");
                Log::error("Gagal menghapus akun alumni {$label}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Selesai. Dihapus: {$dihapus}, dilewati: {$dilewati}, gagal: {$gagal}.");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}