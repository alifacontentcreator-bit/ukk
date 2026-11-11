<?php
/* Tujuan file: Controller ini menangani alur API mutasi rekening untuk aplikasi mobile nasabah:
   pengajuan cetak mutasi, daftar/status permintaan, dan unduhan berkas PDF. */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StatementRequest;
use App\Services\StatementService;
use App\Services\ReportExcelService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    /** Riwayat permintaan mutasi milik nasabah yang sedang login. */
    public function index(Request $request, StatementService $statements): JsonResponse
    {
        $statements->expireStale();

        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $account = $request->user()->account;

        if (!$account) {
            return $this->accountNotFound();
        }

        $page = StatementRequest::where('account_id', $account->id)
            ->latest()
            ->paginate($filters['per_page'] ?? 10);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (StatementRequest $statement) => $this->present($statement))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Nasabah mengajukan cetak mutasi; Teller yang menyiapkan berkas PDF-nya. */
    public function store(Request $request, StatementService $statements): JsonResponse
    {
        $data = $request->validate([
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'period_from.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'period_to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
        ]);

        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return $this->accountNotFound();
        }

        try {
            $period = $statements->resolvePeriod(
                $data['period_from'] ?? null,
                $data['period_to'] ?? null
            );

            $statementRequest = $statements->requestFromCustomer(
                $user,
                $account,
                $period['from'],
                $period['to'],
                $data['reason'] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'STATEMENT_REQUEST_REJECTED',
            ], 422);
        }

        return response()->json([
            'message' => 'Permintaan mutasi dikirim ke petugas. Anda akan menerima notifikasi '
                . 'saat berkas PDF siap diunduh.',
            'code' => 'STATEMENT_REQUEST_SENT',
            'data' => $this->present($statementRequest->refresh()),
        ], 201);
    }

    public function show(Request $request, StatementRequest $statement): JsonResponse
    {
        $this->assertOwner($request, $statement);

        return response()->json(['data' => $this->present($statement)]);
    }

    /** Unduh berkas mutasi (.pdf) yang sudah disiapkan Teller. */
    public function pdf(
        Request $request,
        StatementRequest $statement,
        StatementService $statements
    ): Response {
        $this->assertOwner($request, $statement);

        if (!$statement->isReady()) {
            return response()->json([
                'message' => 'Berkas mutasi belum tersedia. Status: ' . $statement->statusLabel() . '.',
                'code' => 'STATEMENT_NOT_READY',
            ], 409);
        }

        try {
            $bytes = $statements->pdfContents($statement);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'STATEMENT_UNAVAILABLE',
            ], 409);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $statement->fileName() . '"',
        ]);
    }

    /** Unduh berkas mutasi (.xlsx) yang sudah disiapkan Teller. */
    public function excel(
        Request $request,
        StatementRequest $statement,
        StatementService $statements,
        ReportExcelService $excel
    ) {
        $this->assertOwner($request, $statement);

        if (!$statement->isReady()) {
            return response()->json([
                'message' => 'Berkas mutasi belum tersedia. Status: ' . $statement->statusLabel() . '.',
                'code' => 'STATEMENT_NOT_READY',
            ], 409);
        }

        $account = $request->user()->account;
        $data = $statements->build($account, $statement->period_from, $statement->period_to);

        return $excel->download(
            preg_replace('/\.pdf$/', '.xlsx', $statement->fileName()),
            $statements->excelSheets($data)
        );
    }

    private function assertOwner(Request $request, StatementRequest $statement): void
    {
        $account = $request->user()->account;

        abort_unless($account && (int) $statement->account_id === (int) $account->id, 404);
    }

    private function accountNotFound(): JsonResponse
    {
        return response()->json([
            'message' => 'Data rekening tidak ditemukan.',
            'code' => 'ACCOUNT_NOT_FOUND',
        ], 404);
    }

    private function present(StatementRequest $statement): array
    {
        return [
            'id' => $statement->id,
            'request_code' => $statement->request_code,
            'status' => $statement->status,
            'status_label' => $statement->statusLabel(),
            'period_from' => $statement->period_from?->toDateString(),
            'period_to' => $statement->period_to?->toDateString(),
            'period_label' => $statement->periodLabel(),
            'requested_at' => $statement->created_at?->toIso8601String(),
            'processed_at' => $statement->processed_at?->toIso8601String(),
            'note' => $statement->note,
            'can_download' => $statement->isReady(),
            'download_url' => $statement->isReady()
                ? route('api.statements.pdf', $statement)
                : null,
            'excel_url' => $statement->isReady()
                ? route('api.statements.excel', $statement)
                : null,
        ];
    }
}
