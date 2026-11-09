<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * =============================================================================
 * FILE INI UNTUK: Mencatat aktivitas transaksi ke tabel `audit_logs`.
 * =============================================================================
 *
 * Dipakai oleh:
 * - TransactionController        → saat Teller membuat permintaan transaksi (gagal/berhasil kirim).
 * - CustomerTransactionApprovalController → saat nasabah konfirmasi PIN (berhasil/gagal).
 * - SupervisorController         → saat Supervisor approve/reject atau validasi koreksi.
 * - TransactionPostingService    → saat transaksi benar-benar berhasil diposting.
 *
 * Tiga jenis aktivitas yang dicatat (dipakai sebagai "keterangan" di Jurnal Audit):
 * - ACTION_DIAJUKAN → "Menunggu" konfirmasi PIN nasabah.
 * - ACTION_BERHASIL → "Berhasil".
 * - ACTION_GAGAL    → "Gagal" (disertai alasannya).
 *
 * Catatan: penulisan log TIDAK PERNAH menggagalkan transaksi nasabah
 * (semua error penulisan ditangkap lalu dilaporkan).
 */
class TransactionActivityLogger
{
    /** Permintaan transaksi dibuat, menunggu konfirmasi PIN nasabah. */
    public const ACTION_DIAJUKAN = 'transaksi.diajukan';

    /** Transaksi berhasil diposting (saldo sudah berubah). */
    public const ACTION_BERHASIL = 'transaksi.berhasil';

    /** Percobaan transaksi gagal/ditolak. */
    public const ACTION_GAGAL = 'transaksi.gagal';

    /** Nilai kolom `module` pada audit_logs untuk aktivitas transaksi. */
    public const MODULE = 'transaksi';

    /** Catat aktivitas "menunggu konfirmasi". */
    public static function diajukan(?int $idPetugas, array $detailTransaksi, string $keterangan): void
    {
        self::write($idPetugas, self::ACTION_DIAJUKAN, 'success', $detailTransaksi, $keterangan);
    }

    /** Catat aktivitas "transaksi berhasil". */
    public static function berhasil(?int $idPetugas, array $detailTransaksi, string $keterangan): void
    {
        self::write($idPetugas, self::ACTION_BERHASIL, 'success', $detailTransaksi, $keterangan);
    }

    /** Catat aktivitas "transaksi gagal" beserta alasannya. */
    public static function gagal(?int $idPetugas, array $detailTransaksi, string $alasan): void
    {
        self::write($idPetugas, self::ACTION_GAGAL, 'failed', $detailTransaksi, $alasan);
    }

    /**
     * Susun keterangan yang mudah dibaca manusia, contoh:
     * "Setoran • Rp 50.000 • Rek 001-2024-01-01 • Ref REF240101ABCD — PIN salah."
     */
    public static function describe(array $detailTransaksi, string $keterangan): string
    {
        $labelJenis = match ($detailTransaksi['type'] ?? null) {
            'deposit' => 'Setoran',
            'withdrawal' => 'Penarikan',
            default => 'Transaksi',
        };

        $bagianKeterangan = [$labelJenis];

        if (isset($detailTransaksi['amount']) && $detailTransaksi['amount'] !== null && $detailTransaksi['amount'] !== '') {
            $bagianKeterangan[] = 'Rp ' . number_format((float) $detailTransaksi['amount'], 0, ',', '.');
        }

        if (!empty($detailTransaksi['account_number'])) {
            $bagianKeterangan[] = 'Rek ' . $detailTransaksi['account_number'];
        }

        if (!empty($detailTransaksi['reference_number'])) {
            $bagianKeterangan[] = 'Ref ' . $detailTransaksi['reference_number'];
        }

        if (!empty($detailTransaksi['nasabah'])) {
            $bagianKeterangan[] = 'Nasabah ' . $detailTransaksi['nasabah'];
        }

        return implode(' • ', $bagianKeterangan) . ' — ' . $keterangan;
    }

    /** Simpan satu baris jejak audit (dibungkus try/catch agar aman). */
    private static function write(
        ?int $idPetugas,
        string $aksi,
        string $statusAudit,
        array $detailTransaksi,
        string $keterangan
    ): void {
        try {
            $catatanAudit = new AuditLog();
            $catatanAudit->forceFill([
                'user_id' => $idPetugas,
                // Jenis aktivitas sekaligus penanda keterangan:
                // transaksi.diajukan (menunggu), transaksi.berhasil, transaksi.gagal.
                'action' => $aksi,
                'description' => self::describe($detailTransaksi, $keterangan),
            ]);

            // Kolom tambahan hanya diisi bila tersedia pada skema audit_logs
            // (supaya tetap kompatibel dengan tabel audit versi lama/uji minimal).
            if (Schema::hasColumn('audit_logs', 'module')) {
                $catatanAudit->forceFill(['module' => self::MODULE]);
            }

            if (Schema::hasColumn('audit_logs', 'status')) {
                $catatanAudit->forceFill(['status' => $statusAudit]);
            }

            $catatanAudit->save();
        } catch (Throwable $exception) {
            // Jejak audit tidak boleh menggagalkan transaksi nasabah.
            report($exception);
        }
    }
}
