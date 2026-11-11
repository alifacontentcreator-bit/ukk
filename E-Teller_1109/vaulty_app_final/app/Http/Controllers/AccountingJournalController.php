<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur accounting journal controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\AccountingJournal;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountingJournalController extends Controller
{
    //Menampilkan jurnal
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $kategori = $filters['kategori'] ?? 'semua';
        $jurnals = $this->filteredQuery($filters)->get();

        return view('admin.jurnal', compact('jurnals', 'kategori', 'filters'));
    }

    //Menyiapkan halaman cetak

    public function exportPdf(Request $request, ReportPdfService $pdf): Response
    {
        [$filters, $kategori, $jurnals] = $this->exportData($request);

        return response($pdf->render($this->journalDocument($filters, $kategori, $jurnals)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->journalFileName() . '.pdf"',
        ]);
    }

    // Ekspor jurnal akuntansi ke Excel (.xlsx)

    public function exportExcel(Request $request, ReportExcelService $excel)
    {
        [$filters, $kategori, $jurnals] = $this->exportData($request);

        return $excel->download(
            $this->journalFileName() . '.xlsx',
            $this->journalSheets($filters, $kategori, $jurnals)
        );
    }

    /** @return array{0: array<string, mixed>, 1: string, 2: \Illuminate\Support\Collection<int, AccountingJournal>} */
    private function exportData(Request $request): array
    {
        $filters = $this->validatedFilters($request);
        $kategori = $filters['kategori'] ?? 'semua';
        $jurnals = $this->filteredQuery($filters)->get();

        return [$filters, $kategori, $jurnals];
    }

    private function journalFileName(): string
    {
        return 'jurnal-akuntansi-' . now()->format('Ymd-His');
    }

    /** @param \Illuminate\Support\Collection<int, AccountingJournal> $jurnals */
    private function journalRows($jurnals): array
    {
        return $jurnals->map(function (AccountingJournal $jurnal) {
            $user = $jurnal->transaction?->account?->user;

            return [
                $jurnal->created_at?->format('d/m/Y H:i'),
                $jurnal->transaction?->transaction_code ?? '-',
                $user->name ?? 'Sistem',
                ($user && $user->kelas === 'Pegawai') ? 'GURU/KARYAWAN' : 'SISWA',
                $jurnal->account_code,
                strtoupper((string) $jurnal->position),
                (float) $jurnal->amount,
            ];
        })->all();
    }

    private function journalDocument(array $filters, string $kategori, $jurnals): array
    {
        $tanggal = $filters['date'] ?? $filters['date_to'] ?? $filters['date_from'] ?? null;
        $total = (float) $jurnals->sum('amount');

        $rows = array_map(
            fn (array $row) => array_merge(
                array_slice($row, 0, 6),
                ['Rp ' . number_format((float) $row[6], 0, ',', '.')]
            ),
            $this->journalRows($jurnals)
        );

        return [
            'title' => 'LAPORAN JURNAL AKUNTANSI',
            'subtitle' => 'LKM MITRA SISWA ABADI - BANK MINI SMK NEGERI 1 BANTUL',
            'meta' => [
                ['Kategori nasabah', strtoupper($kategori ?: 'SEMUA')],
                ['Tanggal jurnal', $tanggal ? \Carbon\Carbon::parse($tanggal)->format('d/m/Y') : 'Semua tanggal'],
                ['Jumlah baris', (string) $jurnals->count()],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            ],
            'sections' => [[
                'heading' => 'Mutasi Debit dan Kredit',
                'headers' => ['Waktu', 'No. Ref', 'Nama Nasabah', 'Kategori', 'Kode Akun', 'Posisi', 'Nominal'],
                'aligns' => ['left', 'left', 'left', 'left', 'center', 'center', 'right'],
                'widths' => [1.3, 1.4, 1.6, 1.2, 0.9, 0.9, 1.3],
                'rows' => $rows,
                'totals' => [['Total nominal', '', '', '', '', '', 'Rp ' . number_format($total, 0, ',', '.')]],
            ]],
            'notes' => [
                'Total nominal seluruh baris: Rp ' . number_format($total, 0, ',', '.'),
                'Setiap transaksi dicatat pada sisi debit dan kredit sesuai kode akun (111 Kas, 211 Tabungan Nasabah).',
            ],
            'printed_by' => auth()->user()?->name,
            'orientation' => 'landscape',
        ];
    }

    private function journalSheets(array $filters, string $kategori, $jurnals): array
    {
        $tanggal = $filters['date'] ?? $filters['date_to'] ?? $filters['date_from'] ?? null;
        $total = (float) $jurnals->sum('amount');

        $rows = [
            ['LAPORAN JURNAL AKUNTANSI'],
            ['Kategori nasabah', strtoupper($kategori ?: 'SEMUA')],
            ['Tanggal jurnal', $tanggal ? \Carbon\Carbon::parse($tanggal)->format('d/m/Y') : 'Semua tanggal'],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Waktu', 'No. Ref', 'Nama Nasabah', 'Kategori', 'Kode Akun', 'Posisi', 'Nominal'],
        ];

        foreach ($this->journalRows($jurnals) as $row) {
            $rows[] = $row;
        }

        $totalRow = count($rows) + 1;
        $rows[] = ['Total nominal', '', '', '', '', '', $total];

        return [[
            'title' => 'Jurnal Akuntansi',
            'rows' => $rows,
            'header_row' => 6,
            'title_rows' => [1],
            'bold_rows' => [$totalRow],
            'money_columns' => ['G'],
        ]];
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'kategori' => ['nullable', 'in:semua,siswa,guru'],
            'date' => ['nullable', 'date'],
            // Terima query lama agar tautan yang sudah dibagikan tetap berfungsi.
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'name' => ['nullable', 'string', 'max:255'],
            'account_code' => ['nullable', 'string', 'max:10'],
        ]);
    }

    //Mengambil data jurnal dan relasinya

    private function filteredQuery(array $filters)
    {
        $query = AccountingJournal::with(['transaction.account.user'])->latest();

        //Menyaring kategori siswa

        if (($filters['kategori'] ?? 'semua') === 'siswa') {
            $query->whereHas('transaction.account', fn ($account) => $account->where('product_type', 'tabungan_siswa'));
        } elseif (($filters['kategori'] ?? 'semua') === 'guru') {
            $query->whereHas('transaction.account', fn ($account) => $account->where('product_type', 'tabungan_karyawan'));
        }

        //Menyaring tanggal, nama nasabah, dan kode rekening

        $date = $filters['date'] ?? $filters['date_to'] ?? $filters['date_from'] ?? null;
        if ($date) {
            $query->whereDate('accounting_journals.created_at', $date);
        }
        if (!empty($filters['name'])) {
            $name = trim($filters['name']);
            if ($name !== '') {
                $query->whereHas('transaction.account.user', fn ($user) => $user->where('name', 'like', '%' . $name . '%'));
            }
        }
        if (!empty($filters['account_code'])) {
            $accountCode = trim($filters['account_code']);
            if ($accountCode !== '') {
                $query->where('account_code', 'like', '%' . $accountCode . '%');
            }
        }

        return $query;
    }
}