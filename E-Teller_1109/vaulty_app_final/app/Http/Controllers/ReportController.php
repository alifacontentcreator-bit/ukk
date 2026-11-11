<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\DailyReportReopenLog;
use App\Models\DailyReport;
use App\Models\ReportNotification;
use App\Models\User;
use App\Models\Transaction;
use App\Models\CashTransfer;
use App\Models\CashRequest;
use App\Models\SystemSetting;

use App\Services\CashDrawerService;
use App\Services\ReportExcelService;
use App\Services\ReportPdfService;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    // ========================================================
    // 1. BAGIAN SUPERVISOR
    // ========================================================

    public function index(Request $request)
    {
        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:submitted,approved,rejected'],
        ]);

        $query = DailyReport::with([
            'teller',
            'approver',
            'rejecter',
            'reopener',
            'reopenLogs',
            'transaksiPenjelas.account.user',
        ])->whereIn('status', [
            'submitted',
            'approved',
            'rejected',
        ]);

        if (!empty($f['from'])) {
            $query->whereDate('report_date', '>=', $f['from']);
        }

        if (!empty($f['to'])) {
            $query->whereDate('report_date', '<=', $f['to']);
        }

        if (!empty($f['teller_id'])) {
            $query->where('teller_id', $f['teller_id']);
        }

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }

        $reports = $query
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->latest('report_date')
            ->paginate(8)
            ->withQueryString();

        /*
         * Jumlah transaksi setor/tarik pada tanggal laporan, dipakai untuk
         * catatan sistem pada kartu validasi Supervisor.
         */
        $reports->getCollection()->each(function (DailyReport $report) {
            $tanggal = \Carbon\Carbon::parse($report->report_date)->toDateString();

            $trx = Transaction::where('user_id', $report->teller_id)
                ->whereDate('created_at', $tanggal);

            $report->setAttribute('setoran_count', (int) (clone $trx)
                ->where('type', 'deposit')
                ->whereIn('status', ['approved', 'success'])
                ->count());

            $report->setAttribute('penarikan_count', (int) (clone $trx)
                ->where('type', 'withdrawal')
                ->whereIn('status', ['approved', 'success'])
                ->count());
        });

        $tellers = User::where('role', 'teller')
            ->orderBy('name')
            ->get();

        $counts = [
            'menunggu' => DailyReport::where('status', 'submitted')->count(),
            'disetujui' => DailyReport::where('status', 'approved')->count(),
            'ditolak' => DailyReport::where('status', 'rejected')->count(),
            'siap_pindah' => DailyReport::where('status', 'approved')
                ->where('transfer_status', 'pending')
                ->count(),
        ];

        return view('supervisor.laporan', compact(
            'reports',
            'tellers',
            'counts'
        ));
    }

    /**
     * Supervisor menyetujui laporan Teller setelah kas fisik
     * sesuai dengan kas akhir sistem.
     */
    public function approve(Request $request, DailyReport $report)
    {
        try {
            DB::transaction(function () use ($report) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'submitted') {
                    throw new \DomainException(
                        'Laporan ini bukan laporan yang sedang menunggu persetujuan.'
                    );
                }

                $totalPecahanFisik = $report->totalFisik();

                $totalSistem = $report->saldoAkhirSistem();

                $selisih = $totalPecahanFisik - $totalSistem;

                if (abs($selisih) >= 0.01) {
                    throw new \DomainException(
                        'Laporan belum dapat disetujui karena kas fisik dan kas sistem masih memiliki selisih sebesar Rp ' .
                        number_format(abs($selisih), 0, ',', '.') . '.'
                    );
                }

                $report->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejected_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                ]);

                ReportNotification::create([
                    'user_id' => $report->teller_id,
                    'daily_report_id' => $report->id,
                    'title' => 'Laporan kas harian disetujui',
                    'message' => 'Laporan kas harian tanggal '
                        . $this->formatReportDate($report)
                        . ' telah disetujui Supervisor. Kas fisik dan kas sistem sudah sesuai.',
                    'type' => 'report_approved',
                ]);
            });

            return back()->with(
                'success',
                'Laporan berhasil disetujui. Kas fisik dan kas sistem sudah sesuai.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Supervisor menolak laporan Teller, misalnya karena uang fisik di
     * laci masih minus (selisih) dengan catatan sistem.
     *
     * Teller menerima notifikasi berisi alasan penolakan lalu dapat
     * merevisi laporan hariannya dan mengirim ulang.
     */
    public function reject(Request $request, DailyReport $report)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
            'reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        try {
            $selisih = 0.0;

            DB::transaction(function () use ($report, $validated, &$selisih) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'submitted') {
                    throw new \DomainException(
                        'Hanya laporan yang menunggu validasi yang dapat ditolak.'
                    );
                }

                $totalPecahanFisik = $report->totalFisik();

                $totalSistem = $report->saldoAkhirSistem();

                $selisih = $totalPecahanFisik - $totalSistem;

                $report->update([
                    'status' => 'rejected',
                    'rejected_reason' => $validated['reason'],
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'approved_by' => null,
                    'approved_at' => null,
                ]);

                ReportNotification::create([
                    'user_id' => $report->teller_id,
                    'daily_report_id' => $report->id,
                    'title' => 'Laporan kas harian ditolak',
                    'message' => 'Laporan kas harian tanggal '
                        . $this->formatReportDate($report)
                        . ' ditolak Supervisor'
                        . (abs($selisih) >= 0.01
                            ? ' karena terdapat selisih kas sebesar Rp '
                                . number_format(abs($selisih), 0, ',', '.')
                            : '')
                        . '. Alasan: ' . $validated['reason']
                        . ' Silakan revisi laporan harian lalu kirim ulang.',
                    'type' => 'report_rejected',
                ]);
            });

            return back()->with(
                'success',
                'Laporan ditolak. Teller sudah menerima notifikasi untuk merevisi laporan harian.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format tanggal laporan untuk teks notifikasi.
     */
    private function formatReportDate(DailyReport $report): string
    {
        return \Carbon\Carbon::parse($report->report_date)
            ->locale('id')
            ->translatedFormat('d F Y');
    }

    /**
     * Supervisor membuka kembali laporan yang ditolak.
     */
    public function reopen(Request $request, DailyReport $report)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembukaan kembali wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ]);

        try {
            DB::transaction(function () use ($report, $validated) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'rejected') {
                    throw new \DomainException(
                        'Hanya laporan yang ditolak yang dapat dibuka kembali.'
                    );
                }

                DailyReportReopenLog::create([
                    'daily_report_id' => $report->id,
                    'supervisor_id' => auth()->id(),
                    'reason' => $validated['reason'],
                    'reopened_at' => now(),
                ]);

                $report->update([
                    'status' => 'draft',
                    'approved_by' => null,
                    'approved_at' => null,
                    'reopened_at' => now(),
                    'reopened_by' => auth()->id(),
                    'reopen_reason' => $validated['reason'],
                ]);
            });

            return back()->with(
                'success',
                'Laporan berhasil dibuka kembali. Teller dapat memperbaiki laporan.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cetak laporan kas harian Supervisor.
     */
    public function print(Request $request)
    {
        return view('supervisor.laporan-harian-cetak', $this->dailyRecapData($request));
    }

    /** Unduh rekap kas harian Supervisor sebagai PDF. */
    public function printPdf(Request $request, ReportPdfService $pdf): Response
    {
        $data = $this->dailyRecapData($request);

        return response($pdf->render($this->dailyRecapDocument($data)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->dailyRecapFileName($data) . '.pdf"',
        ]);
    }

    /** Unduh rekap kas harian Supervisor sebagai Excel. */
    public function printExcel(Request $request, ReportExcelService $excel)
    {
        $data = $this->dailyRecapData($request);

        return $excel->download(
            $this->dailyRecapFileName($data) . '.xlsx',
            $this->dailyRecapSheets($data)
        );
    }

    /** @return array<string, mixed> */
    private function dailyRecapData(Request $request): array
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = $validated['tanggal'] ?? today()->toDateString();

        $tx = Transaction::whereDate('created_at', $tanggal);

        $setoran = (clone $tx)
            ->where('type', 'deposit')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $penarikan = (clone $tx)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $administrasi = 0;

        // Saldo awal brankas berlaku terus (tidak per tanggal), sedangkan
        // saldo akhir dihitung kumulatif sampai tanggal cetak.
        $cashService = app(CashDrawerService::class);

        $saldoAwalSistem = $cashService->vaultInitialBalance();

        $saldoAkhirSistem = $cashService->supervisorBalance($cashService->vaultBaseline(), $tanggal);

        return compact(
            'tanggal',
            'saldoAwalSistem',
            'setoran',
            'penarikan',
            'administrasi',
            'saldoAkhirSistem'
        );
    }

    private function dailyRecapFileName(array $data): string
    {
        return 'rekap-kas-harian-' . str_replace('-', '', (string) $data['tanggal']);
    }

    /** @return array<string, mixed> */
    private function dailyRecapDocument(array $data): array
    {
        $tanggal = \Carbon\Carbon::parse($data['tanggal']);
        $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');

        return [
            'title' => 'REKAP KAS HARIAN BANK MINI',
            'subtitle' => $tanggal->locale('id')->translatedFormat('l, d F Y'),
            'meta' => [
                ['Tanggal laporan', $tanggal->format('d/m/Y')],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
                ['Saldo awal brankas', $rp($data['saldoAwalSistem'])],
                ['Saldo akhir sistem', $rp($data['saldoAkhirSistem'])],
            ],
            'sections' => [
                [
                    'heading' => 'Rekapitulasi Kas',
                    'headers' => ['Uraian', 'Nominal'],
                    'aligns' => ['left', 'right'],
                    'widths' => [3, 1],
                    'rows' => [
                        ['Setoran (dana masuk)', $rp($data['setoran'])],
                        ['Penarikan (dana keluar)', $rp($data['penarikan'])],
                        ['Biaya administrasi', $rp($data['administrasi'])],
                    ],
                    'totals' => [
                        ['Saldo akhir sistem', $rp($data['saldoAkhirSistem'])],
                    ],
                ],
            ],
            'notes' => [
                'Saldo awal brankas: ' . $rp($data['saldoAwalSistem']),
                'Laporan ini merangkum seluruh transaksi teller pada tanggal di atas.',
            ],
            'printed_by' => auth()->user()?->name,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function dailyRecapSheets(array $data): array
    {
        return [[
            'title' => 'Rekap Kas',
            'rows' => [
                ['REKAP KAS HARIAN BANK MINI'],
                ['Tanggal', \Carbon\Carbon::parse($data['tanggal'])->format('d/m/Y')],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
                [],
                ['Uraian', 'Nominal'],
                ['Setoran (dana masuk)', (float) $data['setoran']],
                ['Penarikan (dana keluar)', (float) $data['penarikan']],
                ['Biaya administrasi', (float) $data['administrasi']],
                ['Saldo awal brankas', (float) $data['saldoAwalSistem']],
                ['Saldo akhir sistem', (float) $data['saldoAkhirSistem']],
            ],
            'header_row' => 5,
            'title_rows' => [1],
            'bold_rows' => [10],
            'money_columns' => ['B'],
        ]];
    }

    /**
     * Cetak rekap laporan harian (penutupan kas) satu Teller.
     *
     * Dipakai Supervisor (semua laporan) dan Teller (laporan sendiri).
     * Hasil cetakan berisi rekapitulasi kas, terbilang, rincian lembaran
     * saldo awal & uang fisik, daftar transaksi, serta kolom tanda tangan.
     */
    public function printDailyReport(DailyReport $report)
    {
        return view('reports.laporan-harian-cetak', $this->dailyReportData($report));
    }

    /** Unduh rekap laporan harian Teller sebagai PDF. */
    public function printDailyReportPdf(DailyReport $report, ReportPdfService $pdf): Response
    {
        $data = $this->dailyReportData($report);

        return response($pdf->render($this->dailyReportDocument($data)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->dailyReportBaseName($data) . '.pdf"',
        ]);
    }

    /** Unduh rekap laporan harian Teller sebagai Excel. */
    public function printDailyReportExcel(DailyReport $report, ReportExcelService $excel)
    {
        $data = $this->dailyReportData($report);

        return $excel->download(
            $this->dailyReportBaseName($data) . '.xlsx',
            $this->dailyReportSheets($data)
        );
    }

    private function dailyReportBaseName(array $data): string
    {
        $report = $data['report'];
        $teller = preg_replace('/[^A-Za-z0-9]+/', '-', $report->teller->name ?? 'teller');

        return 'laporan-harian-'
            . \Carbon\Carbon::parse($report->report_date)->format('Ymd')
            . '-' . trim((string) $teller, '-');
    }

    /**
     * Guard akses + seluruh data yang dipakai tampilan, PDF, dan Excel.
     *
     * @return array<string, mixed>
     */
    private function dailyReportData(DailyReport $report): array
    {
        $user = auth()->user();

        if ($user->role === 'teller' && (int) $report->teller_id !== (int) $user->id) {
            abort(403, 'Anda tidak berhak mencetak laporan Teller lain.');
        }

        $report->loadMissing(['teller', 'approver', 'rejecter', 'transaksiPenjelas']);

        $tanggalLaporan = \Carbon\Carbon::parse($report->report_date)->toDateString();

        /*
         * Draf belum menyimpan hasil hitung uang fisik dan angka mutasinya
         * dapat berubah setelah ada transaksi baru. Untuk cetakan draf,
         * angka rekap disegarkan (tanpa disimpan) supaya cetakan tidak
         * menampilkan selisih palsu seperti "uang fisik Rp0".
         */
        if ($report->status === 'draft') {
            $modalTerbaru = $this->confirmedOpeningTotal((int) $report->teller_id, $tanggalLaporan);

            $report->forceFill(array_merge(
                $this->reportData(
                    $tanggalLaporan,
                    $report->transaksiPenjelas->pluck('id')->all(),
                    (int) $report->teller_id
                ),
                ['saldo_awal' => $modalTerbaru > 0 ? $modalTerbaru : $report->saldo_awal]
            ));
        }

        $transactions = Transaction::with(['account.user'])
            ->where('user_id', $report->teller_id)
            ->whereDate('created_at', $tanggalLaporan)
            ->whereIn('status', ['approved', 'success', 'corrected'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $statusLabel = [
            'draft' => 'Draf',
            'submitted' => 'Menunggu validasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ][$report->status] ?? $report->status;

        $catatanVerifikasi = match ($report->status) {
            'approved' => 'Diverifikasi oleh ' . ($report->approver->name ?? 'Supervisor Bank Mini')
                . ' pada ' . optional($report->approved_at)->format('d/m/Y H:i')
                . '. Catatan: ' . ($report->rejected_reason ?: 'Sesuai'),
            'rejected' => 'Ditolak oleh ' . ($report->rejecter->name ?? 'Supervisor Bank Mini')
                . ' pada ' . optional($report->rejected_at)->format('d/m/Y H:i')
                . '. Catatan: ' . ($report->rejected_reason ?: '-'),
            'submitted' => 'Laporan sudah dikirim dan menunggu verifikasi Supervisor.',
            default => 'Laporan masih draf dan belum dikirim ke Supervisor.',
        };

        $terbilang = \App\Support\Terbilang::rupiah(
            $report->totalFisik() > 0
                ? $report->totalFisik()
                : $report->saldoAkhirSistem()
        );

        $backUrl = $user->role === 'teller'
            ? route('teller.laporan', ['tanggal' => \Carbon\Carbon::parse($report->report_date)->toDateString()])
            : route('supervisor.laporan');

        $pdfUrl = $user->role === 'teller'
            ? route('teller.laporan.pdf', $report)
            : route('supervisor.laporan.pdf', $report);

        $excelUrl = $user->role === 'teller'
            ? route('teller.laporan.excel', $report)
            : route('supervisor.laporan.excel', $report);

        $totalFisik = $report->totalFisik();
        $saldoAkhir = $report->saldoAkhirSistem();
        $selisih = $report->selisihKas();
        $fisikTerhitung = $totalFisik > 0;
        $pengembalian = (float) $report->deposit_total
            - (float) $report->withdrawal_total
            - (float) $report->expected_cash;
        $jumlahSetoran = $transactions->where('type', 'deposit')->count();
        $rincianAwal = $report->rincianFisikAwal();
        $rincianFisik = $report->rincianFisik();

        return compact(
            'report',
            'transactions',
            'statusLabel',
            'catatanVerifikasi',
            'terbilang',
            'backUrl',
            'pdfUrl',
            'excelUrl',
            'totalFisik',
            'saldoAkhir',
            'selisih',
            'fisikTerhitung',
            'pengembalian',
            'jumlahSetoran',
            'rincianAwal',
            'rincianFisik'
        );
    }

    /** @return array<string, mixed> */
    private function dailyReportDocument(array $data): array
    {
        $report = $data['report'];
        $rp = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
        $tanggal = \Carbon\Carbon::parse($report->report_date);

        $rekapRows = [
            ['Saldo awal', $rp($report->saldo_awal)],
            ['Total setoran (' . $data['jumlahSetoran'] . ' transaksi)', $rp($report->deposit_total)],
            ['Total penarikan', $rp($report->withdrawal_total)],
        ];

        if ((float) $data['pengembalian'] > 0) {
            $rekapRows[] = ['Kas diserahkan ke brankas', '- ' . $rp($data['pengembalian'])];
        }

        $rekapRows[] = [
            'Uang fisik di laci',
            $data['fisikTerhitung'] ? $rp($data['totalFisik']) : 'Belum dihitung',
        ];
        $rekapRows[] = [
            'Selisih kas',
            $data['fisikTerhitung']
                ? (abs((float) $data['selisih']) < 0.01
                    ? $rp(0)
                    : ((float) $data['selisih'] > 0 ? '+ ' : '- ') . $rp(abs((float) $data['selisih'])))
                : '-',
        ];

        $rincian = [];
        foreach ($data['rincianAwal'] as $baris) {
            $rincian[] = ['Saldo awal', $rp($baris['nominal']), $baris['jumlah'] . ' ' . $baris['satuan'], $rp($baris['total'])];
        }
        foreach ($data['rincianFisik'] as $baris) {
            $rincian[] = ['Uang fisik', $rp($baris['nominal']), $baris['jumlah'] . ' ' . $baris['satuan'], $rp($baris['total'])];
        }

        $transaksi = $data['transactions']->map(fn ($trx) => [
            $trx->created_at?->format('d/m/Y H:i'),
            $trx->reference_number ?: $trx->transaction_code,
            $trx->type === 'deposit' ? 'Setor tunai' : 'Tarik tunai',
            $trx->account?->account_number ?? '-',
            $trx->account?->user?->name ?? 'Anonim',
            $rp($trx->amount),
        ])->all();

        $notes = [
            'Terbilang: ' . $data['terbilang'] . ($data['fisikTerhitung'] ? '' : ' (kas akhir sistem)'),
            'Catatan verifikasi: ' . $data['catatanVerifikasi'],
        ];

        if ($data['fisikTerhitung'] && $report->catatan_selisih) {
            $notes[] = 'Catatan teller: ' . $report->catatan_selisih;
        }

        if ($data['fisikTerhitung'] && $report->transaksiPenjelas->isNotEmpty()) {
            $notes[] = 'Penjelasan selisih: transaksi hari ini ' . $report->transaksiPenjelas
                ->map(fn ($trx) => '#' . $trx->id . ' '
                    . ($trx->type === 'deposit' ? 'Setor tunai' : 'Tarik tunai')
                    . ' ' . $rp($trx->amount))
                ->implode(', ');
        }

        return [
            'title' => 'LAPORAN HARIAN TELLER (PENUTUPAN KAS)',
            'subtitle' => $tanggal->locale('id')->translatedFormat('l, d F Y')
                . ' - Teller: ' . ($report->teller->name ?? 'Teller'),
            'meta' => [
                ['Tanggal', $tanggal->format('d/m/Y')],
                ['Status', $data['statusLabel']],
                ['Teller', $report->teller->name ?? 'Teller'],
                ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            ],
            'sections' => [
                [
                    'heading' => 'Rekapitulasi Kas',
                    'headers' => ['Uraian', 'Nominal'],
                    'aligns' => ['left', 'right'],
                    'widths' => [3, 1],
                    'rows' => $rekapRows,
                    'totals' => [['Saldo akhir sistem', $rp($data['saldoAkhir'])]],
                ],
                [
                    'heading' => 'Rincian Lembaran (Saldo Awal & Uang Fisik)',
                    'headers' => ['Jenis', 'Nominal', 'Jumlah', 'Total'],
                    'aligns' => ['left', 'right', 'right', 'right'],
                    'widths' => [1.4, 1.2, 1.2, 1.3],
                    'rows' => $rincian,
                ],
                [
                    'heading' => 'Transaksi',
                    'headers' => ['Waktu', 'Referensi', 'Jenis', 'No. Rekening', 'Nasabah', 'Nominal'],
                    'aligns' => ['left', 'left', 'left', 'left', 'left', 'right'],
                    'widths' => [1.2, 1.4, 1.1, 1.5, 1.8, 1.3],
                    'rows' => $transaksi,
                ],
            ],
            'notes' => $notes,
            'printed_by' => auth()->user()?->name,
            'orientation' => 'landscape',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function dailyReportSheets(array $data): array
    {
        $report = $data['report'];

        $rekap = [
            ['LAPORAN HARIAN TELLER (PENUTUPAN KAS)'],
            ['Tanggal', \Carbon\Carbon::parse($report->report_date)->format('d/m/Y')],
            ['Teller', $report->teller->name ?? 'Teller'],
            ['Status', $data['statusLabel']],
            ['Dicetak', now()->format('d/m/Y H:i') . ' WIB'],
            [],
            ['Uraian', 'Nominal'],
            ['Saldo awal', (float) $report->saldo_awal],
            ['Total setoran (' . $data['jumlahSetoran'] . ' transaksi)', (float) $report->deposit_total],
            ['Total penarikan', (float) $report->withdrawal_total],
        ];

        if ((float) $data['pengembalian'] > 0) {
            $rekap[] = ['Kas diserahkan ke brankas', -1 * (float) $data['pengembalian']];
        }

        $rekap[] = ['Uang fisik di laci', $data['fisikTerhitung'] ? (float) $data['totalFisik'] : 'Belum dihitung'];
        $rekap[] = ['Selisih kas', $data['fisikTerhitung'] ? (float) $data['selisih'] : '-'];
        $saldoAkhirRow = count($rekap) + 1;
        $rekap[] = ['Saldo akhir sistem', (float) $data['saldoAkhir']];
        $rekap[] = [];
        $rekap[] = ['Terbilang', $data['terbilang']];
        $rekap[] = ['Catatan verifikasi', $data['catatanVerifikasi']];

        if ($report->catatan_selisih) {
            $rekap[] = ['Catatan teller', $report->catatan_selisih];
        }

        $rincian = [['Jenis', 'Nominal', 'Jumlah', 'Satuan', 'Total']];

        foreach ($data['rincianAwal'] as $baris) {
            $rincian[] = ['Saldo awal', (float) $baris['nominal'], (int) $baris['jumlah'], $baris['satuan'], (float) $baris['total']];
        }

        foreach ($data['rincianFisik'] as $baris) {
            $rincian[] = ['Uang fisik', (float) $baris['nominal'], (int) $baris['jumlah'], $baris['satuan'], (float) $baris['total']];
        }

        if (count($rincian) === 1) {
            $rincian[] = ['Saldo awal', (float) $report->saldo_awal, '-', '-', (float) $report->saldo_awal];
        }

        $transaksi = [['Waktu', 'Referensi', 'Jenis', 'No. Rekening', 'Nasabah', 'Nominal']];

        foreach ($data['transactions'] as $trx) {
            $transaksi[] = [
                $trx->created_at?->format('d/m/Y H:i'),
                $trx->reference_number ?: $trx->transaction_code,
                $trx->type === 'deposit' ? 'Setor tunai' : 'Tarik tunai',
                $trx->account?->account_number ?? '-',
                $trx->account?->user?->name ?? 'Anonim',
                (float) $trx->amount,
            ];
        }

        return [
            [
                'title' => 'Rekap',
                'rows' => $rekap,
                'header_row' => 7,
                'title_rows' => [1],
                'bold_rows' => [$saldoAkhirRow],
                'money_columns' => ['B'],
            ],
            [
                'title' => 'Rincian Lembaran',
                'rows' => $rincian,
                'header_row' => 1,
                'money_columns' => ['B', 'E'],
            ],
            [
                'title' => 'Transaksi',
                'rows' => $transaksi,
                'header_row' => 1,
                'money_columns' => ['F'],
            ],
        ];
    }

    // ========================================================
    // 2. PENGELOLAAN KAS SUPERVISOR
    // ========================================================

    /**
     * Menetapkan / memperbarui saldo awal brankas.
     *
     * Nilai ini adalah modal dasar brankas yang berlaku terus-menerus.
     * Saldo brankas saat ini dihitung dari nilai ini ditambah kas yang
     * kembali ke brankas lalu dikurangi modal yang diserahkan ke Teller,
     * sehingga nilainya tetap bisa diperbarui kapan saja (mis. saat
     * uang fisik di brankas ditambah).
     */
    public function setVaultInitialBalance(Request $request)
    {
        $validated = $request->validate([
            'initial_balance' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $cashService = app(CashDrawerService::class);

                SystemSetting::updateOrCreate(
                    ['key' => CashDrawerService::SETTING_VAULT_INITIAL],
                    ['value' => (string) $validated['initial_balance']]
                );

                // Pergerakan kas yang sudah ada dianggap sudah tercermin pada angka
                // yang diisi, sehingga saldo brankas saat ini = saldo awal ini.
                $cashService->syncVaultBaseline((float) $validated['initial_balance']);
            });

            return back()->with(
                'success',
                'Saldo awal brankas berhasil disimpan. Saldo brankas saat ini sama dengan angka tersebut.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Saldo awal brankas gagal disimpan.',
            ]);
        }
    }

    /**
     * Supervisor menyerahkan modal kepada Teller.
     */
    public function createOpeningTransfer(Request $request)
    {
        $validated = $request->validate([
            'teller_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $date = today()->toDateString();

                $teller = User::whereKey($validated['teller_id'])
                    ->where('role', 'teller')
                    ->lockForUpdate()
                    ->first();

                if (!$teller) {
                    throw new \DomainException(
                        'Pengguna yang dipilih bukan Teller.'
                    );
                }

                $cashService = app(CashDrawerService::class);

                if (!$cashService->hasVaultInitialBalance()) {
                    throw new \DomainException(
                        'Supervisor harus menetapkan saldo awal brankas terlebih dahulu.'
                    );
                }

                $existing = CashTransfer::where('teller_id', $teller->id)
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $date)
                    ->where('status', 'pending')
                    ->first();

                if ($existing) {
                    throw new \DomainException(
                        'Masih ada modal yang belum dikonfirmasi Teller (' .
                        'Rp ' . number_format((float) $existing->amount, 0, ',', '.') .
                        '). Tunggu Teller mengonfirmasi sebelum menyerahkan modal tambahan.'
                    );
                }

                // Saldo brankas dihitung kumulatif (bukan hanya transaksi hari ini).
                $vaultBalance = $cashService->supervisorBalance(null, $date);

                if ((float) $validated['amount'] > $vaultBalance) {
                    throw new \DomainException(
                        'Saldo brankas tidak mencukupi untuk penyerahan modal. ' .
                        'Saldo brankas saat ini Rp ' .
                        number_format($vaultBalance, 0, ',', '.') . '.'
                    );
                }

                $transfer = CashTransfer::create([
                    'teller_id' => $teller->id,
                    'type' => 'opening',
                    'transfer_date' => $date,
                    'amount' => $validated['amount'],
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                    'description' => $validated['description'] ?? null,
                ]);

                // Permintaan tambah modal Teller yang masih pending dianggap terpenuhi.
                CashRequest::where('teller_id', $teller->id)
                    ->whereDate('request_date', $date)
                    ->where('status', CashRequest::STATUS_PENDING)
                    ->update([
                        'status' => CashRequest::STATUS_FULFILLED,
                        'cash_transfer_id' => $transfer->id,
                        'handled_by' => auth()->id(),
                        'handled_at' => now(),
                        'updated_at' => now(),
                    ]);
            });

            return back()->with(
                'success',
                'Penyerahan modal tercatat. Teller harus mengonfirmasi penerimaan.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Penyerahan modal gagal dibuat. Periksa data dan struktur tabel.',
            ]);
        }
    }

    /**
     * Teller mengajukan tambahan modal kepada Supervisor.
     *
     * Teller tidak menentukan nominal. Supervisor yang memutuskan jumlah
     * modal tambahan yang diserahkan dari brankas.
     */
    public function requestOpeningModal(Request $request)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $tellerId = (int) auth()->id();
                $date = today()->toDateString();

                User::whereKey($tellerId)->lockForUpdate()->firstOrFail();

                // Masih ada modal yang belum dikonfirmasi → belum perlu minta lagi.
                $pendingOpening = CashTransfer::where('teller_id', $tellerId)
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $date)
                    ->where('status', 'pending')
                    ->exists();

                if ($pendingOpening) {
                    throw new \DomainException(
                        'Masih ada modal yang menunggu konfirmasi Anda. Konfirmasi dahulu melalui dashboard.'
                    );
                }

                $pendingRequest = CashRequest::where('teller_id', $tellerId)
                    ->whereDate('request_date', $date)
                    ->where('status', CashRequest::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                if ($pendingRequest) {
                    throw new \DomainException(
                        'Permintaan tambah modal hari ini sudah dikirim dan masih menunggu Supervisor.'
                    );
                }

                CashRequest::create([
                    'teller_id' => $tellerId,
                    'request_date' => $date,
                    'status' => CashRequest::STATUS_PENDING,
                    'note' => $validated['note'] ?? null,
                ]);
            });

            return back()->with(
                'success',
                'Permintaan tambah modal sudah dikirim ke Supervisor. Jumlah modal ditentukan Supervisor.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Permintaan tambah modal gagal dikirim. Silakan coba lagi.',
            ]);
        }
    }

    /**
     * Supervisor memenuhi permintaan tambah modal dari Teller.
     *
     * Nominal ditentukan Supervisor dan dicatat sebagai penyerahan modal
     * tambahan (opening) yang wajib dikonfirmasi Teller.
     */
    public function fulfillOpeningRequest(Request $request, CashRequest $cashRequest)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($validated, $cashRequest) {
                $date = today()->toDateString();

                $permintaan = CashRequest::whereKey($cashRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($permintaan->status !== CashRequest::STATUS_PENDING) {
                    throw new \DomainException(
                        'Permintaan tambah modal ini sudah diproses.'
                    );
                }

                $teller = User::whereKey($permintaan->teller_id)
                    ->where('role', 'teller')
                    ->lockForUpdate()
                    ->first();

                if (!$teller) {
                    throw new \DomainException('Teller pemohon tidak ditemukan.');
                }

                $cashService = app(CashDrawerService::class);

                if (!$cashService->hasVaultInitialBalance()) {
                    throw new \DomainException(
                        'Supervisor harus menetapkan saldo awal brankas terlebih dahulu.'
                    );
                }

                $pendingOpening = CashTransfer::where('teller_id', $teller->id)
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $date)
                    ->where('status', 'pending')
                    ->first();

                if ($pendingOpening) {
                    throw new \DomainException(
                        'Teller masih memiliki modal yang belum dikonfirmasi (Rp ' .
                        number_format((float) $pendingOpening->amount, 0, ',', '.') . ').'
                    );
                }

                $vaultBalance = $cashService->supervisorBalance(null, $date);

                if ((float) $validated['amount'] > $vaultBalance) {
                    throw new \DomainException(
                        'Saldo brankas tidak mencukupi untuk penambahan modal. ' .
                        'Saldo brankas saat ini Rp ' .
                        number_format($vaultBalance, 0, ',', '.') . '.'
                    );
                }

                $transfer = CashTransfer::create([
                    'teller_id' => $teller->id,
                    'type' => 'opening',
                    'transfer_date' => $date,
                    'amount' => $validated['amount'],
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                    'description' => $validated['description']
                        ?? 'Tambahan modal atas permintaan Teller',
                ]);

                $permintaan->update([
                    'status' => CashRequest::STATUS_FULFILLED,
                    'cash_transfer_id' => $transfer->id,
                    'handled_by' => auth()->id(),
                    'handled_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Tambahan modal dicatat. Teller harus mengonfirmasi penerimaan modal tambahan.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Permintaan tambah modal gagal diproses. Periksa data dan struktur tabel.',
            ]);
        }
    }

    // ========================================================
    // 3. KONFIRMASI MODAL OLEH TELLER
    // ========================================================

    /**
     * Teller mengonfirmasi penerimaan modal dari Supervisor.
     */
    public function confirmOpeningTransfer(CashTransfer $transfer)
    {
        try {
            DB::transaction(function () use ($transfer) {
                $transfer = CashTransfer::whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $transfer->teller_id !== (int) auth()->id()) {
                    throw new \DomainException(
                        'Kamu tidak memiliki akses untuk mengonfirmasi modal Teller lain.'
                    );
                }

                if ($transfer->type !== 'opening') {
                    throw new \DomainException(
                        'Transfer ini bukan penyerahan modal.'
                    );
                }

                if ($transfer->status !== 'pending') {
                    throw new \DomainException(
                        'Penyerahan modal ini sudah diproses.'
                    );
                }

                $transfer->update([
                    'status' => 'confirmed',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Modal berhasil dikonfirmasi dan masuk ke kas Teller.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);
        }
    }

    // ========================================================
    // 4. PENGEMBALIAN KAS TELLER KE BRANKAS
    // ========================================================

    /**
     * Teller mengajukan pengembalian kas dari laci ke brankas.
     *
     * Uang baru dianggap keluar dari laci setelah Supervisor
     * mengonfirmasi penerimaan kas.
     */
    public function createClosingTransfer(
        Request $request,
        CashDrawerService $cashService
    ) {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Jumlah kas yang dikembalikan wajib diisi.',
            'amount.numeric' => 'Jumlah kas harus berupa angka.',
            'amount.min' => 'Jumlah kas yang dikembalikan minimal Rp 1.',
            'description.max' => 'Keterangan maksimal 500 karakter.',
        ]);

        try {
            DB::transaction(function () use ($validated, $cashService) {
                $tellerId = (int) auth()->id();
                $date = today()->toDateString();
                $amount = round((float) $validated['amount'], 2);

                $teller = User::whereKey($tellerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($teller->role !== 'teller') {
                    throw new \DomainException(
                        'Hanya Teller yang dapat mengajukan pengembalian kas.'
                    );
                }

                $existing = CashTransfer::where('teller_id', $tellerId)
                    ->where('type', 'closing')
                    ->whereDate('transfer_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === 'pending') {
                    throw new \DomainException(
                        'Pengembalian kas hari ini sudah diajukan dan masih menunggu konfirmasi Supervisor.'
                    );
                }

                if ($existing && $existing->status === 'confirmed') {
                    throw new \DomainException(
                        'Pengembalian kas hari ini sudah dikonfirmasi Supervisor dan tidak dapat diajukan lagi.'
                    );
                }

                $uangDiLaci = $cashService->tellerBalance($tellerId, $date);

                if ($uangDiLaci <= 0) {
                    throw new \DomainException(
                        'Tidak ada kas di laci yang dapat dikembalikan.'
                    );
                }

                if ($amount > $uangDiLaci + 0.01) {
                    throw new \DomainException(
                        'Jumlah pengembalian melebihi kas yang tersedia di laci sebesar Rp ' .
                        number_format($uangDiLaci, 0, ',', '.') . '.'
                    );
                }

                $data = [
                    'amount' => $amount,
                    'status' => 'pending',
                    'description' => $validated['description'] ?? null,
                    'created_by' => $tellerId,
                    'confirmed_by' => null,
                    'confirmed_at' => null,
                ];

                if ($existing) {
                    // Baris berstatus "cancelled" dipakai ulang agar tidak
                    // melanggar unique (teller_id, type, transfer_date).
                    $existing->update($data);
                } else {
                    CashTransfer::create(array_merge($data, [
                        'teller_id' => $tellerId,
                        'type' => 'closing',
                        'transfer_date' => $date,
                    ]));
                }
            });

            return back()->with(
                'success',
                'Pengembalian kas diajukan. Serahkan uang kepada Supervisor untuk dikonfirmasi.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Pengembalian kas gagal diajukan. Periksa data dan struktur tabel.',
            ]);
        }
    }

    /**
     * Supervisor mengonfirmasi kas pengembalian dari Teller
     * benar-benar sudah diterima di brankas.
     */
    public function confirmClosingTransfer(CashTransfer $transfer)
    {
        try {
            DB::transaction(function () use ($transfer) {
                $transfer = CashTransfer::whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transfer->type !== 'closing') {
                    throw new \DomainException(
                        'Transfer ini bukan pengembalian kas dari Teller.'
                    );
                }

                if ($transfer->status === 'confirmed') {
                    throw new \DomainException(
                        'Pengembalian kas ini sudah dikonfirmasi.'
                    );
                }

                if ($transfer->status !== 'pending') {
                    throw new \DomainException(
                        'Pengembalian kas ini sudah dibatalkan dan tidak dapat dikonfirmasi.'
                    );
                }

                $transfer->update([
                    'status' => 'confirmed',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Kas pengembalian Teller sudah dikonfirmasi masuk ke brankas.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Konfirmasi pengembalian kas gagal disimpan.',
            ]);
        }
    }

    /**
     * Supervisor mencatat kas laporan harian yang sudah disetujui
     * benar-benar dipindahkan ke brankas.
     */
    public function transferToVault(DailyReport $report)
    {
        try {
            DB::transaction(function () use ($report) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'approved') {
                    throw new \DomainException(
                        'Hanya laporan yang sudah disetujui yang dapat dipindahkan kasnya ke brankas.'
                    );
                }

                if ($report->transfer_status === 'approved') {
                    throw new \DomainException(
                        'Kas laporan ini sudah tercatat di brankas.'
                    );
                }

                $reportDate = \Carbon\Carbon::parse($report->report_date)->toDateString();

                /*
                 * Kas fisik laporan (modal tersisa + setoran - penarikan) yang
                 * benar-benar pindah dari laci Teller ke brankas. Pencatatan ini
                 * membuat saldo brankas bertambah setelah laporan disetujui.
                 *
                 * Kas yang sudah dikembalikan Teller pada tanggal yang sama
                 * dikurangkan agar tidak terhitung dua kali.
                 */
                $kasFisik = round((float) $report->totalFisik(), 2);

                $sudahKembali = (float) CashTransfer::where('teller_id', $report->teller_id)
                    ->where('type', 'closing')
                    ->where('status', 'confirmed')
                    ->whereDate('transfer_date', $reportDate)
                    ->sum('amount');

                $kasMasuk = max(0.0, round($kasFisik - $sudahKembali, 2));

                if ($kasMasuk > 0) {
                    CashTransfer::create([
                        'teller_id' => $report->teller_id,
                        'daily_report_id' => $report->id,
                        'type' => 'closing',
                        'transfer_date' => $reportDate,
                        'amount' => $kasMasuk,
                        'status' => 'confirmed',
                        'created_by' => auth()->id(),
                        'confirmed_by' => auth()->id(),
                        'confirmed_at' => now(),
                        'description' => 'Kas laporan harian '
                            . $this->formatReportDate($report)
                            . ' masuk ke brankas',
                    ]);
                }

                $report->update([
                    'transfer_status' => 'approved',
                    'transferred_by' => auth()->id(),
                    'transferred_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Kas laporan sudah tercatat masuk ke brankas dan menambah saldo brankas.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'laporan' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'laporan' => 'Kas laporan gagal dicatat ke brankas.',
            ]);
        }
    }

    // ========================================================
    // 5. LAPORAN HARIAN TELLER
    // ========================================================

    /**
     * Halaman laporan harian Teller.
     */
    public function tellerIndex(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = today()->toDateString();
        $requestedDate = $validated['tanggal'] ?? $today;

        // Laporan yang masih bisa diperbaiki Teller: draf dan laporan
        // yang ditolak Supervisor (harus direvisi lalu dikirim ulang).
        $hasOpenReport = DailyReport::where('teller_id', auth()->id())
            ->whereDate('report_date', $requestedDate)
            ->whereIn('status', ['draft', 'rejected'])
            ->exists();
        $tanggal = $requestedDate === $today || $hasOpenReport ? $requestedDate : $today;
        $tellerName = auth()->user()->name;

        $tx = Transaction::with('account.user')
            ->where('user_id', auth()->id())
            ->whereDate('created_at', $tanggal);

        $reportHariIni = DailyReport::where('teller_id', auth()->id())
            ->whereDate('report_date', $tanggal)
            ->with('transaksiPenjelas')
            ->first();

        /*
         * Transaksi yang sudah dipilih Teller sebagai penjelasan selisih
         * ikut dihitung walau statusnya belum final, supaya angka kas
         * sistem pada halaman ini sama dengan rekap yang dicetak.
         */
        $transaksiTerpilih = $reportHariIni
            ? $reportHariIni->transaksiPenjelas->pluck('id')->all()
            : [];

        $statusTerhitung = function ($query) use ($transaksiTerpilih) {
            $query->whereIn('status', ['approved', 'success']);

            if ($transaksiTerpilih !== []) {
                $query->orWhereIn('id', $transaksiTerpilih);
            }
        };

        $setoran = (clone $tx)
            ->where('type', 'deposit')
            ->where($statusTerhitung)
            ->sum('amount');

        $penarikan = (clone $tx)
            ->where('type', 'withdrawal')
            ->where($statusTerhitung)
            ->sum('amount');

        // Jumlah transaksi per jenis (dipakai pada catatan sistem di form).
        $jumlahSetoran = (clone $tx)
            ->where('type', 'deposit')
            ->where($statusTerhitung)
            ->count();

        $jumlahPenarikan = (clone $tx)
            ->where('type', 'withdrawal')
            ->where($statusTerhitung)
            ->count();

        $transactions = (clone $tx)->latest()->get();

        $administrasi = 0;

        // Total modal yang diterima hari itu (bisa lebih dari satu penyerahan).
        $saldoAwalSistem = $this->confirmedOpeningTotal(null, $tanggal);

        // Kas yang sudah dikembalikan Teller ke brankas hari ini.
        $pengembalian = (float) CashTransfer::where('teller_id', auth()->id())
            ->whereDate('transfer_date', $tanggal)
            ->where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');

        $saldoAkhirSistem =
            $saldoAwalSistem + $setoran + $administrasi - $penarikan - $pengembalian;

        $reports = DailyReport::where('teller_id', auth()->id())
            ->latest('report_date')
            ->latest()
            ->get();

        // Draf dan laporan yang ditolak masih dapat diperbaiki Teller.
        $editableReports = $reports->whereIn('status', ['draft', 'rejected']);

        $unreadReportCount = ReportNotification::unreadCountFor((int) auth()->id());

        $summary = [
            'deposit_total' => $setoran,
            'withdrawal_total' => $penarikan,
            'transaction_count' => (clone $tx)->whereIn('status', ['approved', 'success', 'corrected'])->count(),
        ];

        return view('teller.laporan-harian', compact(
            'reportHariIni',
            'transactions',
            'reports',
            'editableReports',
            'unreadReportCount',
            'summary',
            'tanggal',
            'tellerName',
            'saldoAwalSistem',
            'setoran',
            'penarikan',
            'pengembalian',
            'administrasi',
            'saldoAkhirSistem',
            'transaksiTerpilih',
            'jumlahSetoran',
            'jumlahPenarikan'
        ));
    }

    /**
     * Halaman notifikasi Teller.
     *
     * Berisi keputusan Supervisor atas laporan kas harian yang sudah
     * dikirim: disetujui atau ditolak beserta alasannya.
     */
    public function notifications()
    {
        $unreadIds = ReportNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->pluck('id');

        $notifications = ReportNotification::with('dailyReport')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        // Notifikasi dianggap terbaca setelah halaman ini dibuka,
        // sehingga angka pada ikon lonceng kembali ke nol.
        ReportNotification::whereIn('id', $unreadIds)
            ->update(['read_at' => now()]);

        return view('teller.notifikasi', compact('notifications', 'unreadIds'));
    }

    /**
     * Teller merevisi laporan harian yang ditolak Supervisor.
     *
     * Laporan dikembalikan ke status draf agar Teller dapat menghitung
     * ulang uang fisik kas akhir lalu mengirim laporan kembali sampai
     * tidak ada selisih (dapat disetujui Supervisor).
     */
    public function reviseTeller(DailyReport $report)
    {
        $tanggal = null;

        try {
            DB::transaction(function () use ($report, &$tanggal) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $report->teller_id !== (int) auth()->id()) {
                    throw new \DomainException(
                        'Kamu tidak memiliki akses untuk merevisi laporan Teller lain.'
                    );
                }

                if ($report->status !== 'rejected') {
                    throw new \DomainException(
                        'Hanya laporan yang ditolak Supervisor yang dapat direvisi.'
                    );
                }

                $tanggal = \Carbon\Carbon::parse($report->report_date)->toDateString();

                // Rekap transaksi dihitung ulang agar angka terbaru
                // langsung terlihat saat Teller memperbaiki laporan.
                // Transaksi penjelas selisih yang sudah dipilih tetap
                // diperhitungkan supaya kas sistem tidak berubah.
                $data = $this->reportData(
                    $tanggal,
                    $report->transaksiPenjelas()->pluck('transactions.id')->all()
                );

                // Modal bisa bertambah setelah draft dibuat; segarkan saldo awal
                // agar laporan memakai total modal terbaru.
                $modalTerbaru = $this->confirmedOpeningTotal((int) auth()->id(), $tanggal);

                if ($modalTerbaru > 0) {
                    $data['saldo_awal'] = $modalTerbaru;
                }

                $report->update(array_merge($data, [
                    'status' => 'draft',
                    'rejected_reason' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                ]));
            });

            return redirect()
                ->route('teller.laporan', ['tanggal' => $tanggal])
                ->with(
                    'success',
                    'Laporan dibuka untuk direvisi. Periksa kembali uang fisik kas akhir lalu kirim ulang ke Supervisor.'
                );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Menghitung mutasi transaksi harian Teller.
     * Nilai expected_cash adalah setoran dikurangi penarikan dan
     * pengembalian kas ke brankas, belum termasuk saldo awal modal Teller.
     *
     * @param  array<int, int>  $transaksiPenjelas  Transaksi yang dipilih Teller
     *                                              sebagai penjelasan selisih kas.
     * @param  int|null  $tellerId  Teller yang dihitung (default: user login).
     */
    private function reportData(?string $date = null, array $transaksiPenjelas = [], ?int $tellerId = null): array
    {
        $date ??= today()->toDateString();
        $tellerId ??= (int) auth()->id();

        $tx = Transaction::where('user_id', $tellerId)
            ->whereDate('created_at', $date);

        /*
         * Status final (approved/success) otomatis dihitung. Transaksi yang
         * dipilih Teller sebagai penjelasan selisih ikut dihitung walau
         * statusnya belum final (mis. hasil koreksi), agar kas akhir sistem
         * sejalan dengan uang fisik yang benar-benar ada di laci.
         */
        $terhitung = function ($query) use ($transaksiPenjelas) {
            $query->whereIn('status', ['approved', 'success']);

            if ($transaksiPenjelas !== []) {
                $query->orWhereIn('id', $transaksiPenjelas);
            }
        };

        $deposit = (clone $tx)
            ->where('type', 'deposit')
            ->where($terhitung)
            ->sum('amount');

        $withdrawal = (clone $tx)
            ->where('type', 'withdrawal')
            ->where($terhitung)
            ->sum('amount');

        // Kas yang sudah dikembalikan Teller ke brankas pada hari ini.
        $closing = CashTransfer::where('teller_id', $tellerId)
            ->whereDate('transfer_date', $date)
            ->where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');

        return [
            'deposit_total' => $deposit,
            'withdrawal_total' => $withdrawal,
            'transaction_count' => (clone $tx)->whereIn('status', ['approved', 'success', 'corrected'])->count(),
            'expected_cash' => $deposit - $withdrawal - $closing,
        ];
    }

    /**
     * Total modal yang sudah dikonfirmasi Teller pada satu tanggal.
     *
     * Dijumlahkan karena modal bisa diserahkan beberapa kali dalam sehari
     * (modal awal + tambahan modal ketika kas laci menipis).
     */
    private function confirmedOpeningTotal(?int $tellerId = null, ?string $date = null): float
    {
        $date ??= today()->toDateString();

        return (float) CashTransfer::where('teller_id', $tellerId ?? auth()->id())
            ->whereDate('transfer_date', $date)
            ->where('type', 'opening')
            ->where('status', 'confirmed')
            ->sum('amount');
    }

    /**
     * Teller membuat draft laporan harian.
     */
    public function createTeller()
    {
        try {
            DB::transaction(function () {
                $tellerId = auth()->id();
                $date = today()->toDateString();

                User::whereKey($tellerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $report = DailyReport::where('teller_id', $tellerId)
                    ->whereDate('report_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($report && $report->status !== 'draft') {
                    throw new \DomainException(
                        'Laporan sudah dikirim atau diproses. Teller tidak dapat membuat ulang draft.'
                    );
                }

                $modalHariIni = $this->confirmedOpeningTotal($tellerId, $date);

                if ($modalHariIni <= 0) {
                    throw new \DomainException(
                        'Modal hari ini belum dikonfirmasi oleh Teller.'
                    );
                }

                // Transaksi penjelas selisih (bila sudah pernah dipilih)
                // tetap diperhitungkan agar angka draft tidak berubah.
                $transaksiPenjelas = $report
                    ? $report->transaksiPenjelas()->pluck('transactions.id')->all()
                    : [];

                $data = array_merge($this->reportData($date, $transaksiPenjelas), [
                    'saldo_awal' => $modalHariIni,
                ]);

                if ($report) {
                    $report->update($data);
                } else {
                    DailyReport::create(array_merge($data, [
                        'teller_id' => $tellerId,
                        'report_date' => $date,
                        'status' => 'draft',
                        'transfer_status' => 'pending',
                    ]));
                }
            });

            return back()->with(
                'success',
                'Draft laporan hari ini berhasil dibuat atau diperbarui.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Teller mengirim laporan akhir kepada Supervisor.
     */
    public function sendTeller(Request $request)
    {
        // Aturan validasi dibangun dari daftar pecahan pada model agar
        // penambahan nominal baru (Rp10.000 s.d. Rp50) otomatis ikut
        // tervalidasi tanpa mengubah method ini.
        $aturan = ['tanggal' => ['nullable', 'date_format:Y-m-d']];

        foreach (DailyReport::PECAHAN_FISIK as $pecahan) {
            $aturan[$pecahan['kolom']] = in_array($pecahan['kolom'], DailyReport::PECAHAN_WAJIB, true)
                ? ['required', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'];
        }

        // Transaksi hari itu yang dipilih sebagai penjelasan selisih kas.
        $aturan['transaksi_penjelas'] = ['nullable', 'array'];
        $aturan['transaksi_penjelas.*'] = ['integer', 'min:1'];

        // Catatan Teller atas selisih kas (wajib bila selisih tidak nol).
        $aturan['catatan_selisih'] = ['nullable', 'string', 'max:500'];

        $validated = $request->validate($aturan, [
            'pecahan_100k.required' => 'Jumlah uang pecahan Rp100.000 wajib diisi.',
            'pecahan_50k.required' => 'Jumlah uang pecahan Rp50.000 wajib diisi.',
            'pecahan_20k.required' => 'Jumlah uang pecahan Rp20.000 wajib diisi.',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $tellerId = auth()->id();
                $date = $validated['tanggal'] ?? today()->toDateString();

                User::whereKey($tellerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $report = DailyReport::where('teller_id', $tellerId)
                    ->whereDate('report_date', $date)
                    ->lockForUpdate()
                    ->first();

                if (!$report) {
                    throw new \DomainException(
                        'Buat draft laporan terlebih dahulu.'
                    );
                }

                if ($report->status !== 'draft') {
                    throw new \DomainException(
                        'Laporan sudah dikirim atau diproses. Laporan tidak dapat dikirim ulang.'
                    );
                }

                $modalHariIni = $this->confirmedOpeningTotal($tellerId, $date);

                if ($modalHariIni <= 0) {
                    throw new \DomainException(
                        'Modal hari ini belum dikonfirmasi.'
                    );
                }

                $legacyPending = Transaction::where('user_id', $tellerId)
                    ->whereDate('created_at', $date)
                    ->where('status', 'pending')
                    ->count();

                if ($legacyPending > 0) {
                    throw new \DomainException(
                        "Masih ada {$legacyPending} transaksi yang belum diproses. Selesaikan terlebih dahulu."
                    );
                }

                $rincianFisik = $this->jumlahPecahan($validated, DailyReport::PECAHAN_FISIK);
                $totalFisik = $this->totalPecahan($rincianFisik, DailyReport::PECAHAN_FISIK);

                /*
                 * Transaksi yang boleh dipilih sebagai penjelas hanyalah
                 * transaksi Teller tersebut pada tanggal laporan.
                 */
                $kandidat = Transaction::where('user_id', $tellerId)
                    ->whereDate('created_at', $date)
                    ->where('status', '!=', 'void')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $transaksiPenjelas = array_values(array_unique(array_map(
                    'intval',
                    (array) ($validated['transaksi_penjelas'] ?? [])
                )));

                if (array_diff($transaksiPenjelas, $kandidat) !== []) {
                    throw new \DomainException(
                        'Transaksi penjelas selisih harus transaksi Anda pada tanggal laporan ini.'
                    );
                }

                // Kas seharusnya = saldo awal (modal) + setoran - penarikan.
                // Transaksi penjelas yang dipilih ikut dihitung.
                $data = $this->reportData($date, $transaksiPenjelas);

                $kasSeharusnya = $modalHariIni + (float) $data['expected_cash'];

                // Selisih = uang fisik - kas seharusnya.
                // Positif = kas lebih, negatif = kas kurang.
                $selisih = $totalFisik - $kasSeharusnya;

                $catatan = trim((string) ($validated['catatan_selisih'] ?? ''));

                /*
                 * Selisih tidak nol wajib dijelaskan lewat catatan Teller
                 * sebelum laporan dikirim ke Supervisor.
                 */
                if (abs($selisih) >= 0.01 && $catatan === '') {
                    throw new \DomainException(
                        'Selisih kas Rp ' . number_format(abs($selisih), 0, ',', '.')
                        . ' (' . ($selisih > 0 ? 'kas lebih' : 'kas kurang') . '). '
                        . 'Isi catatan teller sebagai penjelasan sebelum mengirim laporan.'
                    );
                }

                $report->update(array_merge($data, $rincianFisik, [
                    'saldo_awal' => $modalHariIni,
                    'catatan_selisih' => $catatan !== '' ? $catatan : null,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]));

                $report->transaksiPenjelas()->sync($transaksiPenjelas);
            });

            return back()->with(
                'success',
                'Laporan kas akhir berhasil dikirim kepada Supervisor.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mengambil jumlah lembar/keping yang diisi Teller dari input.
     *
     * @param  array<string, mixed>  $input
     * @param  array<int, array{nominal: int, kolom: string, satuan: string}>  $daftar
     * @return array<string, int>
     */
    private function jumlahPecahan(array $input, array $daftar): array
    {
        $jumlah = [];

        foreach ($daftar as $pecahan) {
            $jumlah[$pecahan['kolom']] = (int) ($input[$pecahan['kolom']] ?? 0);
        }

        return $jumlah;
    }

    /**
     * Total rupiah dari jumlah lembar/keping yang sudah dihitung.
     *
     * @param  array<string, int>  $jumlah
     * @param  array<int, array{nominal: int, kolom: string, satuan: string}>  $daftar
     */
    private function totalPecahan(array $jumlah, array $daftar): int
    {
        $total = 0;

        foreach ($daftar as $pecahan) {
            $total += ($jumlah[$pecahan['kolom']] ?? 0) * $pecahan['nominal'];
        }

        return $total;
    }

    /**
     * Alias untuk route lama.
     */
    public function storeTeller(Request $request)
    {
        return $this->sendTeller($request);
    }
}
