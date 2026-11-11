<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur admin audit log controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use App\Services\TransactionActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $f = $this->validatedFilters($request);

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

    /** Unduh Log & Audit ke PDF. */
    public function exportPdf(Request $request, ReportPdfService $pdf): Response
    {
        $data = $this->exportData($request);

        return response($pdf->render($this->exportDocument($data)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="log-audit-' . now()->format('Ymd-His') . '.pdf"',
        ]);
    }

    /** Unduh Log & Audit ke Excel. */
    public function exportExcel(Request $request, ReportExcelService $excel)
    {
        $data = $this->exportData($request);

        return $excel->download(
            'log-audit-' . now()->format('Ymd-His') . '.xlsx',
            $this->exportSheets($data)
        );
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'jenis' => ['nullable', 'in:semua,transaksi,transaksi_berhasil,transaksi_gagal,transaksi_menunggu,sistem'],
            'q' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
    }

    /** @return array<string, mixed> */
    private function exportData(Request $request): array
    {
        $f = $this->validatedFilters($request);

        $jenis = $f['jenis'] ?? 'semua';
        $q = trim((string) ($f['q'] ?? ''));
        $from = $f['from'] ?? null;
        $to = $f['to'] ?? null;

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

        $logs = $logQuery->orderByDesc('created_at')->orderByDesc('id')->limit(5000)->get();

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

        $transaksi = $transaksiQuery->orderByDesc('created_at')->orderByDesc('id')->limit(5000)->get();

        return compact('jenis', 'q', 'from', 'to', 'logs', 'transaksi');
    }

    /** @return array<int, array<string, mixed>> */
    private function exportSheets(array $data): array
    {
        $logRows = [
            ['LOG AKTIVITAS'],
            ['Jenis aktivitas', ucfirst((string) $data['jenis'])],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Waktu', 'Petugas', 'Aksi', 'Keterangan'],
        ];

        foreach ($data['logs'] as $log) {
            $logRows[] = [
                $log->created_at?->format('d/m/Y H:i'),
                $log->user?->name ?? 'Sistem',
                $this->aksiLabel((string) $log->action),
                $log->description ?: '-',
            ];
        }

        $trxRows = [
            ['TRANSAKSI TERCATAT'],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Waktu', 'Referensi', 'Nasabah', 'Teller', 'Jenis', 'Status', 'Nominal'],
        ];

        foreach ($data['transaksi'] as $tx) {
            $trxRows[] = [
                $tx->created_at?->format('d/m/Y H:i'),
                $tx->reference_number ?: $tx->transaction_code,
                $tx->account?->user?->name ?? '-',
                $tx->teller?->name ?? '-',
                $tx->type === 'deposit' ? 'Setoran' : 'Penarikan',
                match ($tx->status) {
                    'approved', 'success' => 'Berhasil',
                    'rejected', 'void' => 'Gagal',
                    'pending' => 'Menunggu',
                    'corrected' => 'Dikoreksi',
                    default => ucfirst((string) $tx->status),
                },
                (float) $tx->amount,
            ];
        }

        return [
            [
                'title' => 'Log Aktivitas',
                'rows' => $logRows,
                'header_row' => 5,
                'title_rows' => [1],
            ],
            [
                'title' => 'Transaksi',
                'rows' => $trxRows,
                'header_row' => 4,
                'title_rows' => [1],
                'money_columns' => ['G'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function exportDocument(array $data): array
    {
        $logs = $data['logs']->map(fn (AuditLog $log) => [
            $log->created_at?->format('d/m/Y H:i'),
            $log->user?->name ?? 'Sistem',
            $this->aksiLabel((string) $log->action),
            $log->description ?: '-',
        ])->all();

        $transaksi = $data['transaksi']->map(fn (Transaction $tx) => [
            $tx->created_at?->format('d/m/Y H:i'),
            $tx->reference_number ?: $tx->transaction_code,
            $tx->account?->user?->name ?? '-',
            $tx->teller?->name ?? '-',
            $tx->type === 'deposit' ? 'Setoran' : 'Penarikan',
            match ($tx->status) {
                'approved', 'success' => 'Berhasil',
                'rejected', 'void' => 'Gagal',
                'pending' => 'Menunggu',
                'corrected' => 'Dikoreksi',
                default => ucfirst((string) $tx->status),
            },
            'Rp ' . number_format((float) $tx->amount, 0, ',', '.'),
        ])->all();

        return [
            'title' => 'LOG & AUDIT SISTEM',
            'subtitle' => 'LKM MITRA SISWA ABADI - BANK MINI SMK NEGERI 1 BANTUL',
            'meta' => [
                ['Jenis aktivitas', ucfirst((string) $data['jenis'])],
                ['Periode', (!empty($data['from']) ? \Carbon\Carbon::parse($data['from'])->format('d/m/Y') : '-')
                    . ' s/d ' . (!empty($data['to']) ? \Carbon\Carbon::parse($data['to'])->format('d/m/Y') : '-')],
                ['Pencarian', $data['q'] !== '' ? $data['q'] : '-'],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            ],
            'sections' => [
                [
                    'heading' => 'Log Aktivitas',
                    'headers' => ['Waktu', 'Petugas', 'Aksi', 'Keterangan'],
                    'aligns' => ['left', 'left', 'left', 'left'],
                    'widths' => [1.1, 1.3, 1.6, 3],
                    'rows' => $logs,
                ],
                [
                    'heading' => 'Transaksi Tercatat',
                    'headers' => ['Waktu', 'Referensi', 'Nasabah', 'Teller', 'Jenis', 'Status', 'Nominal'],
                    'aligns' => ['left', 'left', 'left', 'left', 'left', 'left', 'right'],
                    'widths' => [1.1, 1.4, 1.5, 1.2, 0.9, 0.9, 1.2],
                    'rows' => $transaksi,
                ],
            ],
            'notes' => [
                'Log audit bersifat baca-saja; data tidak pernah diubah atau dihapus dari halaman ini.',
            ],
            'printed_by' => auth()->user()?->name,
            'orientation' => 'landscape',
        ];
    }

    private function aksiLabel(string $action): string
    {
        return [
            TransactionActivityLogger::ACTION_DIAJUKAN => 'Transaksi menunggu konfirmasi',
            TransactionActivityLogger::ACTION_BERHASIL => 'Transaksi berhasil',
            TransactionActivityLogger::ACTION_GAGAL => 'Transaksi gagal',
            'created' => 'Data dibuat',
            'updated' => 'Data diperbarui',
            'deleted' => 'Data dihapus',
        ][$action] ?? $action;
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
