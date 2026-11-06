<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerTransactionApproval;
use App\Services\PinService;
use App\Services\TransactionPostingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerTransactionApprovalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        $approvals = CustomerTransactionApproval::with('teller:id,name')
            ->where('account_id', $account->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->oldest()
            ->get()
            ->map(fn (CustomerTransactionApproval $approval) => [
                'id' => $approval->id,
                'type' => $approval->type,
                'type_label' => $approval->type === 'deposit' ? 'Setoran tunai' : 'Penarikan tunai',
                'amount' => (float) $approval->amount,
                'description' => $approval->description,
                'teller_name' => $approval->teller?->name ?? 'Teller',
                'expires_at' => $approval->expires_at->toIso8601String(),
            ]);

        return response()->json(['data' => $approvals]);
    }

    public function approve(
        Request $request,
        CustomerTransactionApproval $approval,
        PinService $pins,
        TransactionPostingService $posting
    ): JsonResponse {
        $data = $request->validate([
            'pin' => [$approval->type === 'withdrawal' ? 'required' : 'nullable', 'digits:6'],
        ]);
        $user = $request->user();

        try {
            return DB::transaction(function () use ($approval, $data, $user, $pins, $posting) {
                $lockedApproval = CustomerTransactionApproval::whereKey($approval->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $account = Account::whereKey($lockedApproval->account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $account->user_id !== (int) $user->id) {
                    abort(404);
                }

                if ($lockedApproval->status !== 'pending') {
                    return response()->json([
                        'message' => 'Permintaan transaksi ini sudah diproses.',
                        'code' => 'APPROVAL_UNAVAILABLE',
                    ], 409);
                }

                if ($lockedApproval->expires_at->isPast()) {
                    $lockedApproval->update([
                        'status' => 'expired',
                        'processed_at' => now(),
                    ]);

                    return response()->json([
                        'message' => 'Permintaan transaksi sudah kedaluwarsa. Minta Teller membuat permintaan baru.',
                        'code' => 'APPROVAL_EXPIRED',
                    ], 410);
                }

                if ($lockedApproval->type === 'withdrawal') {
                    $check = $pins->verify($account, (string) $data['pin']);

                    if ($check['status'] === 'locked') {
                        $lockedApproval->update([
                            'status' => 'rejected',
                            'processed_at' => now(),
                        ]);

                        return response()->json([
                            'message' => $pins->lockedMessage($check['retry_after']),
                            'code' => $check['reset_required'] ? 'PIN_RESET_REQUIRED' : 'PIN_LOCKED',
                            'retry_after' => $check['retry_after'],
                        ], 423);
                    }

                    if ($check['status'] === 'wrong') {
                        return response()->json([
                            'message' => 'PIN salah. Sisa percobaan: ' . $check['attempts_left'] . '.',
                            'code' => 'PIN_INVALID',
                            'attempts_left' => $check['attempts_left'],
                        ], 422);
                    }
                }

                $account->load('user');
                $teller = $lockedApproval->teller()->firstOrFail();
                $transaction = $posting->post(
                    $account,
                    $teller,
                    $lockedApproval->type,
                    (float) $lockedApproval->amount,
                    $lockedApproval->description
                );

                $lockedApproval->update([
                    'status' => 'approved',
                    'transaction_id' => $transaction->id,
                    'processed_at' => now(),
                ]);

                return response()->json([
                    'message' => 'Transaksi berhasil disetujui.',
                    'code' => 'TRANSACTION_APPROVED',
                    'transaction' => [
                        'id' => $transaction->id,
                        'reference_number' => $transaction->reference_number,
                        'balance' => (float) $account->fresh()->balance,
                    ],
                ]);
            });
        } catch (DomainException $exception) {
            DB::transaction(function () use ($approval) {
                $lockedApproval = CustomerTransactionApproval::whereKey($approval->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedApproval?->status === 'pending') {
                    $lockedApproval->update([
                        'status' => 'rejected',
                        'processed_at' => now(),
                    ]);
                }
            });

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'TRANSACTION_REJECTED',
            ], 422);
        }
    }

    public function reject(
        Request $request,
        CustomerTransactionApproval $approval
    ): JsonResponse {
        return DB::transaction(function () use ($approval, $request) {
            $lockedApproval = CustomerTransactionApproval::whereKey($approval->id)
                ->lockForUpdate()
                ->firstOrFail();

            $account = Account::whereKey($lockedApproval->account_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $account->user_id !== (int) $request->user()->id) {
                abort(404);
            }

            if ($lockedApproval->status !== 'pending') {
                return response()->json([
                    'message' => 'Permintaan transaksi ini sudah diproses.',
                    'code' => 'APPROVAL_UNAVAILABLE',
                ], 409);
            }

            $lockedApproval->update([
                'status' => 'rejected',
                'processed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Permintaan transaksi ditolak. Saldo tidak berubah.',
                'code' => 'TRANSACTION_REJECTED_BY_CUSTOMER',
            ]);
        });
    }
}
