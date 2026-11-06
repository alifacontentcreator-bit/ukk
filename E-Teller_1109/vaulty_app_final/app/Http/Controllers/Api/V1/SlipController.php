<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\SlipFormatter;
use App\Services\SlipPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slip transaksi nasabah (setoran/penarikan yang sudah final).
 *
 * Data diambil langsung dari transaksi yang dicatat teller, sehingga slip
 * otomatis muncul di menu Riwayat Slip setelah teller mencetaknya.
 */
class SlipController extends Controller
{
    private const FINAL_STATUSES = ['approved', 'success'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:deposit,withdrawal'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $account = $request->user()->account;

        if (!$account) {
            return response()->json([
                'message' => 'Data rekening tidak ditemukan.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $query = Transaction::with(['account.user', 'teller'])
            ->withExists('correction')
            ->where('account_id', $account->id)
            ->whereIn('status', self::FINAL_STATUSES)
            ->when(
                !empty($filters['type']),
                fn ($builder) => $builder->where('type', $filters['type'])
            )
            ->orderByRaw('COALESCE(approved_at, created_at) DESC')
            ->orderByDesc('id');

        $page = $query->cursorPaginate($filters['limit'] ?? 15);

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (Transaction $transaction) => SlipFormatter::summary($transaction))
                ->values(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'prev_cursor' => $page->previousCursor()?->encode(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json([
            'data' => SlipFormatter::detail($this->findSlip($request, $uuid)),
        ]);
    }

    public function pdf(
        Request $request,
        string $uuid,
        SlipPdfService $pdf
    ): Response {
        $transaction = $this->findSlip($request, $uuid);
        $bytes = $pdf->render(SlipFormatter::detail($transaction));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="slip-'
                . ($transaction->reference_number ?: $transaction->id) . '.pdf"',
        ]);
    }

    private function findSlip(Request $request, string $uuid): Transaction
    {
        $account = $request->user()->account;

        abort_unless($account, 404);

        $transaction = Transaction::with(['account.user', 'teller'])
            ->withExists('correction')
            ->where('account_id', $account->id)
            ->whereIn('status', self::FINAL_STATUSES)
            ->whereKey((int) $uuid)
            ->first();

        abort_unless($transaction, 404);

        return $transaction;
    }
}
