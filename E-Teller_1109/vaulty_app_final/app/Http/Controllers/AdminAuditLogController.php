<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\TransactionActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Halaman Admin → Log & Audit.
 *
 * Menampilkan seluruh jejak audit sistem, dengan penekanan pada
 * RIWAYAT TRANSAKSI (berhasil maupun gagal) yang ditulis oleh
 * TransactionActivityLogger ke tabel audit_logs.
 *
 * Halaman ini hanya untuk dibaca: tidak ada aksi ubah atau hapus.
 */
class AdminAuditLogController extends Controller
{
    /** Jenis aktivitas transaksi yang dicatat TransactionActivityLogger. */
    private const AKSI_TRANSAKSI = [
        TransactionActivityLogger::ACTION_DIAJUKAN,
        TransactionActivityLogger::ACTION_BERHASIL,
        TransactionActivityLogger::ACTION_GAGAL,
    ];

    public function index(Request $request)
    {
        $f = $request->validate([
            'jenis' => ['nullable', 'in:semua,transaksi,transaksi_berhasil,transaksi_gagal,transaksi_menunggu,sistem'],
            'q' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $jenis = $f['jenis'] ?? 'semua';
        $q = trim((string) ($f['q'] ?? ''));
        $from = $f['from'] ?? null;
        $to = $f['to'] ?? null;

        // ============ 1. Riwayat transaksi: berhasil & gagal ============
        $trxQuery = AuditLog::with('user')->whereIn('action', self::AKSI_TRANSAKSI);

        if ($jenis === 'sistem') {
            // Tab "aktivitas sistem" tidak menampilkan riwayat transaksi.
            $trxQuery->whereRaw('1 = 0');
        } elseif ($jenis !== 'semua' && $jenis !== 'transaksi') {
            $trxQuery->where('action', $this->aksiUntuk($jenis));
        }

        $this->terapkanFilter($trxQuery, $from, $to, $q);

        $riwayatTransaksi = $trxQuery->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'page_transaksi')
            ->withQueryString();

        // ============ 2. Seluruh aktivitas (sistem & transaksi) ============
        $logQuery = AuditLog::with('user');

        if ($jenis === 'sistem') {
            $logQuery->whereNotIn('action', self::AKSI_TRANSAKSI);
        } elseif ($jenis !== 'semua') {
            $logQuery->whereIn('action', self::AKSI_TRANSAKSI);

            if ($jenis !== 'transaksi') {
                $logQuery->where('action', $this->aksiUntuk($jenis));
            }
        }

        $this->terapkanFilter($logQuery, $from, $to, $q);

        $logs = $logQuery->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'page')
            ->withQueryString();

        // ============ 2. Transaksi tercatat (tabel transactions) ============
        // Bagian ini menampilkan riwayat transaksi termasuk data lama yang
        // sudah ada sebelum jejak audit transaksi aktif.
        $transaksiQuery = Transaction::with(['account.user', 'teller']);

        if ($jenis === 'sistem') {
            $transaksiQuery->whereRaw('1 = 0');
        } elseif ($jenis === 'transaksi_berhasil') {
            $transaksiQuery->whereIn('status', ['approved', 'success']);
        } elseif ($jenis === 'transaksi_gagal') {
            $transaksiQuery->whereIn('status', ['rejected', 'void']);
        } elseif ($jenis === 'transaksi_menunggu') {
            $transaksiQuery->where('status', 'pending');
        }

        if ($from) {
            $transaksiQuery->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $transaksiQuery->whereDate('created_at', '<=', $to);
        }

        if ($q !== '') {
            $transaksiQuery->where(function ($w) use ($q) {
                $w->where('reference_number', 'like', "%{$q}%")
                    ->orWhere('transaction_code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('account', function ($a) use ($q) {
                        $a->where('account_number', 'like', "%{$q}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"));
                    });
            });
        }

        $transaksiTercatat = $transaksiQuery->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'page_transaksi_tercatat')
            ->withQueryString();

        // ============ 3. Ringkasan ============
        $ringkasan = [
            'total' => AuditLog::count(),
            'hari_ini' => AuditLog::whereDate('created_at', today())->count(),
            'trx_berhasil' => AuditLog::where('action', TransactionActivityLogger::ACTION_BERHASIL)->count(),
            'trx_gagal' => AuditLog::where('action', TransactionActivityLogger::ACTION_GAGAL)->count(),
            'trx_menunggu' => AuditLog::where('action', TransactionActivityLogger::ACTION_DIAJUKAN)->count(),
            'trx_tercatat' => Transaction::count(),
        ];

        return view('admin.audit-log', compact(
            'logs',
            'riwayatTransaksi',
            'transaksiTercatat',
            'ringkasan',
            'jenis',
            'q',
            'from',
            'to'
        ));
    }

    /** Nama aksi untuk filter jenis transaksi. */
    private function aksiUntuk(string $jenis): string
    {
        return match ($jenis) {
            'transaksi_berhasil' => TransactionActivityLogger::ACTION_BERHASIL,
            'transaksi_gagal' => TransactionActivityLogger::ACTION_GAGAL,
            'transaksi_menunggu' => TransactionActivityLogger::ACTION_DIAJUKAN,
            default => TransactionActivityLogger::ACTION_GAGAL,
        };
    }

    /** Filter tanggal dan kata kunci (pengguna, aksi, keterangan). */
    private function terapkanFilter(Builder $query, ?string $from, ?string $to, string $q): void
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($q === '') {
            return;
        }

        $query->where(function (Builder $w) use ($q) {
            $w->where('action', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhereHas('user', function (Builder $u) use ($q) {
                    $u->where('name', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%");
                });
        });
    }
}
