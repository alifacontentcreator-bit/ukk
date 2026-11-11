<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur transaction history controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionHistoryController extends Controller
{
    /**
     * Waktu yang dipakai untuk urutan & tampilan: saat transaksi DISETUJUI
     * (saat saldo benar-benar berubah). Transaksi lama tanpa approved_at
     * memakai created_at.
     */
    private const EFFECTIVE_TIME = 'COALESCE(approved_at, created_at)';

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', 'in:deposit,withdrawal'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $account = $request->user()->account;

        // 'approved' = setor/tarik yang sudah disetujui supervisor.
        // 'success'  = transaksi koreksi saldo (mengubah saldo juga, jadi harus tampil).
        $query = Transaction::where('account_id', $account->id)
            ->whereIn('status', ['approved', 'success']);

        if (!empty($filters['from'])) {
            $query->whereRaw('DATE(' . self::EFFECTIVE_TIME . ') >= ?', [$filters['from']]);
        }
        if (!empty($filters['to'])) {
            $query->whereRaw('DATE(' . self::EFFECTIVE_TIME . ') <= ?', [$filters['to']]);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $page = $query
            ->orderByRaw(self::EFFECTIVE_TIME . ' DESC')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Transaction $tx) => $this->present($tx))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    private function present(Transaction $tx): array
    {
        $at = $tx->approved_at ?? $tx->created_at;
        $amount = (float) $tx->amount;
        $isDeposit = $tx->type === 'deposit';
        $balanceAfter = data_get($tx->data_after, 'balance');

        return [
            'id' => $tx->id,
            'reference_number' => $tx->reference_number ?: $tx->transaction_code,
            'type' => $tx->type,
            'type_label' => $isDeposit ? 'Setoran' : 'Penarikan',
            'description' => $tx->description,
            'amount' => $amount,
            'signed_amount' => $isDeposit ? $amount : -$amount,
            'balance_after' => $balanceAfter !== null ? (float) $balanceAfter : null,
            'occurred_at' => $at->toIso8601String(),
            'date' => $at->format('d/m/Y'),
            'time' => $at->format('H:i'),
        ];
    }
}