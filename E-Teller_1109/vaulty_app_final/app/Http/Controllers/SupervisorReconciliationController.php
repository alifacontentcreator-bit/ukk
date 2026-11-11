<?php
/* Tujuan file: Controller ini menangani halaman Rekonsiliasi Kas Supervisor dan ekspornya
   ke PDF/Excel: ringkasan brankas serta status kas setiap teller pada tanggal tertentu. */

namespace App\Http\Controllers;

use App\Models\CashRequest;
use App\Models\CashTransfer;
use App\Models\DailyReport;
use App\Models\User;
use App\Services\CashDrawerService;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupervisorReconciliationController extends Controller
{
    public function index(Request $request, CashDrawerService $cashService)
    {
        return view('supervisor.rekonsiliasi', $this->reconciliationData($request, $cashService));
    }

    /** Unduh laporan rekonsiliasi kas sebagai PDF. */
    public function exportPdf(
        Request $request,
        CashDrawerService $cashService,
        ReportPdfService $pdf
    ): Response {
        $data = $this->reconciliationData($request, $cashService);
        $tanggal = \Carbon\Carbon::parse($data['tanggal'])->format('Ymd');

        return response($pdf->render($this->document($data)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rekonsiliasi-kas-' . $tanggal . '.pdf"',
        ]);
    }

    /** Unduh laporan rekonsiliasi kas sebagai Excel. */
    public function exportExcel(
        Request $request,
        CashDrawerService $cashService,
        ReportExcelService $excel
    ) {
        $data = $this->reconciliationData($request, $cashService);
        $tanggal = \Carbon\Carbon::parse($data['tanggal'])->format('Ymd');

        return $excel->download(
            'rekonsiliasi-kas-' . $tanggal . '.xlsx',
            $this->sheets($data)
        );
    }

    /** @return array<string, mixed> */
    private function reconciliationData(Request $request, CashDrawerService $cashService): array
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = $validated['tanggal'] ?? today()->toDateString();

        $totalTeller = User::where('role', 'teller')->count();

        // Data laporan Teller pada tanggal yang dipilih.
        $reports = DailyReport::with('teller')
            ->whereDate('report_date', $tanggal)
            ->get()
            ->map(function ($report) {
                $report->total_fisik = $report->totalFisik();
                $report->total_sistem = $report->saldoAkhirSistem();
                $report->selisih = $report->selisihKas();

                return $report;
            });

        $summary = [
            'total_teller' => $totalTeller,
            'sudah_lapor' => $reports->count(),
            'klop' => $reports->filter(fn ($report) => abs((float) $report->selisih) < 0.01)->count(),
            'selisih' => $reports->filter(fn ($report) => abs((float) $report->selisih) >= 0.01)->count(),
        ];

        $tellers = User::where('role', 'teller')->orderBy('name')->get();

        $vaultInitial = $cashService->vaultInitialBalance();

        $openingTransfers = CashTransfer::with('teller')
            ->where('type', 'opening')
            ->whereDate('transfer_date', $tanggal)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $confirmedOpening = CashTransfer::where('type', 'opening')
            ->whereDate('transfer_date', $tanggal)
            ->where('status', 'confirmed')
            ->sum('amount');

        $closingTransfers = CashTransfer::with('teller')
            ->where('type', 'closing')
            ->whereDate('transfer_date', $tanggal)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $cashRequests = CashRequest::with('teller')
            ->where('status', CashRequest::STATUS_PENDING)
            ->latest()
            ->get();

        $vaultBalanceHitung = $cashService->supervisorBalance();
        $vaultShortage = $vaultBalanceHitung < 0 ? abs($vaultBalanceHitung) : 0.0;
        $vaultBalance = max(0.0, $vaultBalanceHitung);

        return compact(
            'tanggal',
            'summary',
            'reports',
            'tellers',
            'vaultInitial',
            'vaultBalance',
            'vaultShortage',
            'openingTransfers',
            'confirmedOpening',
            'closingTransfers',
            'cashRequests'
        );
    }

    /** @return array<string, mixed> */
    private function document(array $data): array
    {
        $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $statusLabel = fn ($status) => [
            'draft' => 'Draf',
            'submitted' => 'Menunggu validasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ][$status] ?? ucfirst((string) $status);

        $rows = $data['reports']->map(fn (DailyReport $report) => [
            $report->teller->name ?? '-',
            $rp($report->saldo_awal),
            $rp($report->deposit_total),
            $rp($report->withdrawal_total),
            $rp($report->total_sistem),
            $report->total_fisik > 0 ? $rp($report->total_fisik) : 'Belum dihitung',
            abs((float) $report->selisih) < 0.01
                ? $rp(0)
                : ((float) $report->selisih > 0 ? '+ ' : '- ') . $rp(abs((float) $report->selisih)),
            $statusLabel($report->status),
        ])->all();

        $ringkasan = [
            ['Total teller', (string) $data['summary']['total_teller']],
            ['Sudah mengirim laporan', (string) $data['summary']['sudah_lapor']],
            ['Kas klop (selisih Rp0)', (string) $data['summary']['klop']],
            ['Laporan berselisih', (string) $data['summary']['selisih']],
            ['Modal diserahkan (dikonfirmasi)', $rp($data['confirmedOpening'])],
            ['Saldo awal brankas', $rp($data['vaultInitial'])],
            ['Saldo brankas saat ini', $rp($data['vaultBalance'])],
        ];

        if ((float) $data['vaultShortage'] > 0) {
            $ringkasan[] = ['Kekurangan brankas', $rp($data['vaultShortage'])];
        }

        return [
            'title' => 'REKONSILIASI KAS BANK MINI',
            'subtitle' => \Carbon\Carbon::parse($data['tanggal'])->locale('id')->translatedFormat('l, d F Y'),
            'meta' => [
                ['Tanggal', \Carbon\Carbon::parse($data['tanggal'])->format('d/m/Y')],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
                ['Saldo brankas', $rp($data['vaultBalance'])],
                ['Total teller', (string) $data['summary']['total_teller']],
            ],
            'sections' => [
                [
                    'heading' => 'Ringkasan Rekonsiliasi',
                    'headers' => ['Uraian', 'Nilai'],
                    'aligns' => ['left', 'right'],
                    'widths' => [3, 1],
                    'rows' => $ringkasan,
                ],
                [
                    'heading' => 'Status Kas per Teller',
                    'headers' => ['Teller', 'Saldo Awal', 'Setoran', 'Penarikan', 'Kas Sistem', 'Uang Fisik', 'Selisih', 'Status'],
                    'aligns' => ['left', 'right', 'right', 'right', 'right', 'right', 'right', 'left'],
                    'widths' => [1.5, 1, 1, 1, 1, 1.1, 1, 1.1],
                    'rows' => $rows,
                ],
            ],
            'notes' => [
                'Selisih positif berarti kas lebih, selisih negatif berarti kas kurang dari catatan sistem.',
                'Uang fisik "Belum dihitung" berarti Teller belum mengisi rincian pecahan pada laporan tersebut.',
            ],
            'printed_by' => auth()->user()?->name,
            'orientation' => 'landscape',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sheets(array $data): array
    {
        $statusLabel = fn ($status) => [
            'draft' => 'Draf',
            'submitted' => 'Menunggu validasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ][$status] ?? ucfirst((string) $status);

        $rekapRows = [
            ['REKONSILIASI KAS BANK MINI'],
            ['Tanggal', \Carbon\Carbon::parse($data['tanggal'])->format('d/m/Y')],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Uraian', 'Nilai'],
            ['Total teller', (int) $data['summary']['total_teller']],
            ['Sudah mengirim laporan', (int) $data['summary']['sudah_lapor']],
            ['Kas klop (selisih Rp0)', (int) $data['summary']['klop']],
            ['Laporan berselisih', (int) $data['summary']['selisih']],
            ['Modal diserahkan (dikonfirmasi)', (float) $data['confirmedOpening']],
            ['Saldo awal brankas', (float) $data['vaultInitial']],
            ['Saldo brankas saat ini', (float) $data['vaultBalance']],
        ];

        if ((float) $data['vaultShortage'] > 0) {
            $rekapRows[] = ['Kekurangan brankas', (float) $data['vaultShortage']];
        }

        $statusRows = [
            ['STATUS KAS PER TELLER'],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Teller', 'Saldo Awal', 'Setoran', 'Penarikan', 'Kas Sistem', 'Uang Fisik', 'Selisih', 'Status'],
        ];

        foreach ($data['reports'] as $report) {
            $statusRows[] = [
                $report->teller->name ?? '-',
                (float) $report->saldo_awal,
                (float) $report->deposit_total,
                (float) $report->withdrawal_total,
                (float) $report->total_sistem,
                (float) $report->total_fisik,
                (float) $report->selisih,
                $statusLabel($report->status),
            ];
        }

        return [
            [
                'title' => 'Ringkasan',
                'rows' => $rekapRows,
                'header_row' => 5,
                'title_rows' => [1],
                'money_columns' => ['B'],
            ],
            [
                'title' => 'Status Kas',
                'rows' => $statusRows,
                'header_row' => 4,
                'title_rows' => [1],
                'money_columns' => ['B', 'C', 'D', 'E', 'F', 'G'],
            ],
        ];
    }
}
