<?php
/* Tujuan file: Controller ini menangani halaman Cetak Mutasi Teller: pratinjau mutasi rekening,
   unduhan PDF/Excel, serta pemrosesan permintaan mutasi yang diajukan nasabah dari aplikasi mobile. */

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\StatementRequest;
use App\Services\ReportExcelService;
use App\Services\StatementPdfService;
use App\Services\StatementService;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    /**
     * Halaman Cetak Mutasi: form rekening + periode, pratinjau mutasi,
     * dan daftar permintaan mutasi nasabah yang menunggu diproses.
     */
    public function index(Request $request, StatementService $statements)
    {
        $statements->expireStale();

        $filters = [
            'account_number' => $request->query('account_number'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $account = null;
        $statement = null;
        $periodError = null;

        if ($filters['account_number']) {
            try {
                [$account, $statement] = $this->statementFromRequest($request, $statements);
            } catch (DomainException $exception) {
                $periodError = $exception->getMessage();
                $account = Account::with('user')
                    ->where('account_number', $filters['account_number'])
                    ->first();
            }
        }

        $pending = StatementRequest::with(['account.user'])
            ->open()
            ->oldest()
            ->get();

        $history = StatementRequest::with(['account.user', 'processor'])
            ->where('status', '!=', StatementRequest::STATUS_PENDING)
            ->latest()
            ->paginate(8, ['*'], 'riwayat')
            ->withQueryString();

        return view('teller.mutasi', compact('filters', 'account', 'statement', 'periodError', 'pending', 'history'));
    }

    /** Unduh / pratinjau PDF mutasi rekening. */
    public function pdf(
        Request $request,
        StatementService $statements,
        StatementPdfService $pdf
    ): Response {
        [$account, $statement] = $this->statementFromRequest($request, $statements);

        $bytes = $pdf->render($statement);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->fileName($account, $statement, 'pdf') . '"',
        ]);
    }

    /** Unduh mutasi rekening dalam format Excel (.xlsx). */
    public function excel(
        Request $request,
        StatementService $statements,
        ReportExcelService $excel
    ) {
        [$account, $statement] = $this->statementFromRequest($request, $statements);

        return $excel->download(
            $this->fileName($account, $statement, 'xlsx'),
            $statements->excelSheets($statement)
        );
    }

    /** Tampilan siap cetak (browser print) untuk mutasi rekening. */
    public function printView(Request $request, StatementService $statements)
    {
        [$account, $statement] = $this->statementFromRequest($request, $statements);

        return view('teller.mutasi-cetak', compact('account', 'statement'));
    }

    /** Teller mengirim berkas PDF mutasi ke aplikasi nasabah. */
    public function kirim(StatementRequest $statement, StatementService $statements)
    {
        try {
            $ready = $statements->markReady($statement, auth()->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['mutasi' => $exception->getMessage()]);
        }

        return back()->with(
            'success',
            'Berkas mutasi periode ' . $ready->periodLabel() . ' (' . $ready->request_code
                . ') sudah dibuat dan dikirim ke aplikasi nasabah.'
        );
    }

    /** Teller membuka kembali berkas PDF yang sudah dikirim ke nasabah. */
    public function berkas(StatementRequest $statement, StatementService $statements): Response
    {
        if (!$statement->isReady()) {
            abort(404, 'Berkas mutasi belum tersedia.');
        }

        return response($statements->pdfContents($statement), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $statement->fileName() . '"',
        ]);
    }

    /** Teller menolak permintaan mutasi beserta alasannya. */
    public function tolak(Request $request, StatementRequest $statement, StatementService $statements)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        try {
            $statements->reject($statement, auth()->user(), trim($data['reason']));
        } catch (DomainException $exception) {
            return back()->withErrors(['mutasi' => $exception->getMessage()]);
        }

        return back()->with('success', 'Permintaan mutasi ditolak. Alasan sudah dikirim ke nasabah.');
    }

    /**
     * Membangun data mutasi dari parameter query halaman Teller.
     *
     * @return array{0: Account, 1: array<string, mixed>}
     */
    private function statementFromRequest(Request $request, StatementService $statements): array
    {
        $data = $request->validate([
            'account_number' => ['required', 'string', 'max:30'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'account_number.required' => 'Nomor rekening wajib diisi.',
            'from.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
        ]);

        $account = Account::with('user')
            ->where('account_number', $data['account_number'])
            ->first();

        if (!$account) {
            throw new DomainException(
                'Nomor rekening ' . $data['account_number'] . ' tidak ditemukan. Periksa kembali.'
            );
        }

        $period = $statements->resolvePeriod($data['from'] ?? null, $data['to'] ?? null);
        $statements->assertRangeAllowed($period['from'], $period['to']);

        return [$account, $statements->build($account, $period['from'], $period['to'])];
    }

    private function fileName(Account $account, array $statement, string $extension): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-_]/', '', (string) $account->account_number) ?: 'rekening';

        return 'mutasi-' . $number . '-'
            . date('Ymd', strtotime($statement['period_from_iso'])) . '-'
            . date('Ymd', strtotime($statement['period_to_iso'])) . '.' . $extension;
    }
}
