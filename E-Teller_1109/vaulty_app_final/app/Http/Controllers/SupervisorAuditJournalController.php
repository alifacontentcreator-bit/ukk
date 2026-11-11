<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur supervisor audit journal controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use App\Services\TransactionActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * =============================================================================
 * FILE INI UNTUK: Halaman "Jurnal Audit" milik SUPERVISOR.
 * =============================================================================
 *
 * Halaman ini menampilkan tiga hal:
 * 1. Kartu ringkasan jumlah transaksi: total, berhasil, gagal, menunggu.
 * 2. Tabel "Jurnal Transaksi" → seluruh transaksi (berhasil & gagal) + alasannya.
 * 3. Tabel "Aktivitas Transaksi" → jejak audit setiap percobaan transaksi
 *    (diajukan / berhasil / gagal) yang ditulis TransactionActivityLogger.
 *
 * Halaman ini HANYA UNTUK DIBACA (tidak ada ubah/hapus data).
 */
class SupervisorAuditJournalController extends Controller
{
    /** Jenis aktivitas transaksi yang dicatat di tabel audit_logs. */
    private const AKTIVITAS_TRANSAKSI = [
        TransactionActivityLogger::ACTION_DIAJUKAN, // menunggu konfirmasi PIN
        TransactionActivityLogger::ACTION_BERHASIL, // transaksi sukses
        TransactionActivityLogger::ACTION_GAGAL,    // transaksi gagal/ditolak
    ];

    /**
     * Menampilkan halaman Jurnal Audit Supervisor (beserta filter).
     */
    public function index(Request $request)
    {
        // 1. Ambil filter dari URL (tanggal, teller, jenis, keterangan, kata kunci).
        $filter = $this->validatedFilters($request);
        $keteranganFilter = $filter['status'] ?? null;
        $kataKunci = trim((string) ($filter['q'] ?? ''));

        // 2. Query tabel transaksi + terapkan filter.
        $queryTransaksi = $this->transactionQuery($filter);

        // 3. Ringkasan angka untuk kartu di atas halaman.
        $statistik = [
            'total' => (clone $queryTransaksi)->count(),
            'berhasil' => (clone $queryTransaksi)->whereIn('status', ['approved', 'success'])->count(),
            'menunggu' => (clone $queryTransaksi)->where('status', 'pending')->count(),
            'gagal' => (clone $queryTransaksi)->whereIn('status', ['rejected', 'void'])->count(),
        ];

        // Tanggal transaksi paling awal → penanda riwayat tersimpan dari dulu.
        $arsipTransaksiPertama = Transaction::min('created_at');

        $transaksi = $queryTransaksi->latest()->paginate(20)->withQueryString();

        // 4. Jejak audit aktivitas transaksi (berhasil maupun gagal).
        $queryAktivitas = $this->activityQuery($filter);

        $ringkasanAktivitas = [
            'total' => (clone $queryAktivitas)->count(),
            'berhasil' => (clone $queryAktivitas)->where('action', TransactionActivityLogger::ACTION_BERHASIL)->count(),
            'gagal' => (clone $queryAktivitas)->where('action', TransactionActivityLogger::ACTION_GAGAL)->count(),
            'menunggu' => (clone $queryAktivitas)->where('action', TransactionActivityLogger::ACTION_DIAJUKAN)->count(),
        ];

        $aktivitas = $queryAktivitas->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'page_aktivitas')
            ->withQueryString();

        $daftarTeller = User::where('role', 'teller')->orderBy('name')->get();

        return view('supervisor.jurnal', [
            'stats' => $statistik,
            'tellers' => $daftarTeller,
            'transaksi' => $transaksi,
            'awalTransaksi' => $arsipTransaksiPertama,
            'aktivitas' => $aktivitas,
            'ringkasAktivitas' => $ringkasanAktivitas,
        ]);
    }

    /** Unduh Jurnal Audit Supervisor ke PDF. */
    public function exportPdf(Request $request, ReportPdfService $pdf): Response
    {
        $data = $this->exportData($request);

        return response($pdf->render($this->exportDocument($data)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="jurnal-audit-' . now()->format('Ymd-His') . '.pdf"',
        ]);
    }

    /** Unduh Jurnal Audit Supervisor ke Excel. */
    public function exportExcel(Request $request, ReportExcelService $excel)
    {
        $data = $this->exportData($request);

        return $excel->download(
            'jurnal-audit-' . now()->format('Ymd-His') . '.xlsx',
            $this->exportSheets($data)
        );
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'in:deposit,withdrawal'],
            'status' => ['nullable', 'in:berhasil,gagal,menunggu,pending,approved,rejected,success,corrected,void'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);
    }

    private function transactionQuery(array $filter): Builder
    {
        $query = Transaction::with(['account.user', 'teller']);

        $this->terapkanFilterTanggalTeller($query, $filter, 'created_at');
        $this->terapkanFilterJenisKeterangan($query, $filter, $filter['status'] ?? null);
        $this->terapkanFilterKataKunci($query, trim((string) ($filter['q'] ?? '')));

        return $query;
    }

    private function activityQuery(array $filter): Builder
    {
        $query = AuditLog::with('user')->whereIn('action', self::AKTIVITAS_TRANSAKSI);

        $this->terapkanFilterTanggalTeller($query, $filter, 'created_at');
        $this->terapkanFilterAktivitas($query, $filter['status'] ?? null);

        $kataKunci = trim((string) ($filter['q'] ?? ''));
        if ($kataKunci !== '') {
            $query->where('description', 'like', "%{$kataKunci}%");
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function exportData(Request $request): array
    {
        $filter = $this->validatedFilters($request);

        return [
            'filter' => $filter,
            'transaksi' => $this->transactionQuery($filter)->latest()->limit(5000)->get(),
            'aktivitas' => $this->activityQuery($filter)
                ->orderByDesc('created_at')->orderByDesc('id')->limit(5000)->get(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function exportSheets(array $data): array
    {
        $filter = $data['filter'];
        $statusLabel = fn ($status) => [
            'pending' => 'Menunggu', 'approved' => 'Berhasil', 'berhasil' => 'Berhasil',
            'success' => 'Berhasil', 'rejected' => 'Gagal', 'failed' => 'Gagal',
            'void' => 'Dibatalkan', 'corrected' => 'Dikoreksi',
        ][$status] ?? ($status ? ucfirst((string) $status) : '-');

        $trxRows = [
            ['JURNAL TRANSAKSI'],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            ['Filter', $this->filterDescription($filter)],
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
                $statusLabel($tx->status),
                (float) $tx->amount,
            ];
        }

        $activityRows = [
            ['AKTIVITAS TRANSAKSI (JEJAK AUDIT)'],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Waktu', 'Petugas', 'Aktivitas', 'Keterangan'],
        ];

        foreach ($data['aktivitas'] as $log) {
            $activityRows[] = [
                $log->created_at?->format('d/m/Y H:i'),
                $log->user?->name ?? 'Sistem',
                match ($log->action) {
                    TransactionActivityLogger::ACTION_BERHASIL => 'Transaksi berhasil',
                    TransactionActivityLogger::ACTION_GAGAL => 'Transaksi gagal',
                    default => 'Permintaan transaksi',
                },
                $log->description ?: '-',
            ];
        }

        return [
            [
                'title' => 'Jurnal Transaksi',
                'rows' => $trxRows,
                'header_row' => 5,
                'title_rows' => [1],
                'money_columns' => ['G'],
            ],
            [
                'title' => 'Aktivitas',
                'rows' => $activityRows,
                'header_row' => 4,
                'title_rows' => [1],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function exportDocument(array $data): array
    {
        $filter = $data['filter'];
        $statusLabel = fn ($status) => [
            'pending' => 'Menunggu', 'approved' => 'Berhasil', 'berhasil' => 'Berhasil',
            'success' => 'Berhasil', 'rejected' => 'Gagal', 'failed' => 'Gagal',
            'void' => 'Dibatalkan', 'corrected' => 'Dikoreksi',
        ][$status] ?? ($status ? ucfirst((string) $status) : '-');

        $transaksi = $data['transaksi']->map(fn (Transaction $tx) => [
            $tx->created_at?->format('d/m/Y H:i'),
            $tx->reference_number ?: $tx->transaction_code,
            $tx->account?->user?->name ?? '-',
            $tx->teller?->name ?? '-',
            $tx->type === 'deposit' ? 'Setoran' : 'Penarikan',
            $statusLabel($tx->status),
            'Rp ' . number_format((float) $tx->amount, 0, ',', '.'),
        ])->all();

        $aktivitas = $data['aktivitas']->map(fn (AuditLog $log) => [
            $log->created_at?->format('d/m/Y H:i'),
            $log->user?->name ?? 'Sistem',
            match ($log->action) {
                TransactionActivityLogger::ACTION_BERHASIL => 'Transaksi berhasil',
                TransactionActivityLogger::ACTION_GAGAL => 'Transaksi gagal',
                default => 'Permintaan transaksi',
            },
            $log->description ?: '-',
        ])->all();

        return [
            'title' => 'JURNAL AUDIT TRANSAKSI',
            'subtitle' => 'LKM MITRA SISWA ABADI - BANK MINI SMK NEGERI 1 BANTUL',
            'meta' => [
                ['Jumlah transaksi', (string) $data['transaksi']->count()],
                ['Jumlah aktivitas', (string) $data['aktivitas']->count()],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
                ['Filter', $this->filterDescription($filter)],
            ],
            'sections' => [
                [
                    'heading' => 'Jurnal Transaksi',
                    'headers' => ['Waktu', 'Referensi', 'Nasabah', 'Teller', 'Jenis', 'Status', 'Nominal'],
                    'aligns' => ['left', 'left', 'left', 'left', 'left', 'left', 'right'],
                    'widths' => [1.1, 1.4, 1.5, 1.2, 0.9, 0.9, 1.2],
                    'rows' => $transaksi,
                ],
                [
                    'heading' => 'Aktivitas Transaksi (Jejak Audit)',
                    'headers' => ['Waktu', 'Petugas', 'Aktivitas', 'Keterangan'],
                    'aligns' => ['left', 'left', 'left', 'left'],
                    'widths' => [1.1, 1.2, 1.4, 3],
                    'rows' => $aktivitas,
                ],
            ],
            'notes' => [
                'Jurnal audit bersifat baca-saja; data tidak pernah diubah atau dihapus oleh halaman ini.',
            ],
            'printed_by' => auth()->user()?->name,
            'orientation' => 'landscape',
        ];
    }

    private function filterDescription(array $filter): string
    {
        $info = [];

        if (!empty($filter['from']) || !empty($filter['to'])) {
            $info[] = 'Periode '
                . (!empty($filter['from']) ? \Carbon\Carbon::parse($filter['from'])->format('d/m/Y') : '-')
                . ' s/d '
                . (!empty($filter['to']) ? \Carbon\Carbon::parse($filter['to'])->format('d/m/Y') : '-');
        }

        if (!empty($filter['teller_id'])) {
            $info[] = 'Teller: ' . (User::find($filter['teller_id'])?->name ?? '-');
        }

        if (!empty($filter['type'])) {
            $info[] = 'Jenis: ' . ($filter['type'] === 'deposit' ? 'Setoran' : 'Penarikan');
        }

        if (!empty($filter['status'])) {
            $info[] = 'Keterangan: ' . ucfirst((string) $filter['status']);
        }

        if (!empty($filter['q'])) {
            $info[] = 'Pencarian: "' . $filter['q'] . '"';
        }

        return $info === [] ? 'Semua data' : implode(' | ', $info);
    }

    /**
     * Filter tanggal awal/akhir + petugas (teller) tertentu.
     */
    private function terapkanFilterTanggalTeller(Builder $query, array $filter, string $kolomTanggal): void
    {
        if (!empty($filter['from'])) {
            $query->whereDate($kolomTanggal, '>=', $filter['from']);
        }

        if (!empty($filter['to'])) {
            $query->whereDate($kolomTanggal, '<=', $filter['to']);
        }

        if (!empty($filter['teller_id'])) {
            $query->where('user_id', $filter['teller_id']);
        }
    }

    /**
     * Filter jenis transaksi (setoran/penarikan) dan keterangan
     * berhasil/gagal/menunggu pada tabel transaksi.
     */
    private function terapkanFilterJenisKeterangan(Builder $query, array $filter, ?string $keterangan): void
    {
        if (!empty($filter['type'])) {
            $query->where('type', $filter['type']);
        }

        // Keterangan ringkas: Berhasil (approved/success), Gagal (rejected/void),
        // Menunggu (pending). Nilai status lama tetap diterima.
        if ($keterangan === 'berhasil') {
            $query->whereIn('status', ['approved', 'success']);
        } elseif ($keterangan === 'gagal') {
            $query->whereIn('status', ['rejected', 'void']);
        } elseif ($keterangan === 'menunggu') {
            $query->where('status', 'pending');
        } elseif (!empty($keterangan)) {
            $query->where('status', $keterangan);
        }
    }

    /**
     * Pencarian pada jurnal transaksi:
     * nomor referensi, kode transaksi, nomor rekening, atau nama nasabah.
     */
    private function terapkanFilterKataKunci(Builder $query, string $kataKunci): void
    {
        if ($kataKunci === '') {
            return;
        }

        $query->where(function (Builder $subQuery) use ($kataKunci) {
            $subQuery->where('reference_number', 'like', "%{$kataKunci}%")
                ->orWhere('transaction_code', 'like', "%{$kataKunci}%")
                ->orWhereHas('account', function (Builder $akun) use ($kataKunci) {
                    $akun->where('account_number', 'like', "%{$kataKunci}%")
                        ->orWhereHas('user', fn (Builder $nasabah) => $nasabah->where('name', 'like', "%{$kataKunci}%"));
                });
        });
    }

    /**
     * Filter tabel aktivitas berdasarkan keterangan yang dipilih,
     * supaya isinya konsisten dengan tabel transaksi.
     */
    private function terapkanFilterAktivitas(Builder $query, ?string $keterangan): void
    {
        if ($keterangan === 'berhasil') {
            $query->where('action', TransactionActivityLogger::ACTION_BERHASIL);
        } elseif ($keterangan === 'gagal') {
            $query->where('action', TransactionActivityLogger::ACTION_GAGAL);
        } elseif ($keterangan === 'menunggu') {
            $query->where('action', TransactionActivityLogger::ACTION_DIAJUKAN);
        }
    }
}
