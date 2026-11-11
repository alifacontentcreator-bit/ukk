<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur customer transaction approval controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerTransactionApproval;
use App\Services\PinService;
use App\Services\TransactionActivityLogger;
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
                'reference_number' => $approval->reference_number,
                'type' => $approval->type,
                'type_label' => $approval->type === 'deposit' ? 'Setoran Tunai' : 'Penarikan Tunai',
                'amount' => (float) $approval->amount,
                'description' => $approval->description,
                'teller_name' => $approval->teller?->name ?? 'Teller',
                'requested_at' => $approval->created_at?->toIso8601String(),
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
            'pin' => ['required', 'digits:6'],
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

                    // Jurnal Audit: percobaan gagal karena kedaluwarsa.
                    TransactionActivityLogger::gagal($lockedApproval->teller_id, [
                        'type' => $lockedApproval->type,
                        'amount' => $lockedApproval->amount,
                        'account_number' => $account->account_number,
                        'reference_number' => $lockedApproval->reference_number,
                        'nasabah' => $account->user?->name,
                    ], 'Kedaluwarsa: nasabah tidak mengonfirmasi PIN sebelum batas waktu.');

                    return response()->json([
                        'message' => 'Permintaan transaksi sudah kedaluwarsa. Minta Teller membuat permintaan baru.',
                        'code' => 'APPROVAL_EXPIRED',
                    ], 410);
                }

                /*
                 * PIN wajib untuk SETORAN maupun PENARIKAN. Setoran ikut
                 * dikonfirmasi nasabah supaya saldo hanya bertambah setelah
                 * nasabah menyetujui permintaan Teller.
                 */
                $check = $pins->verify($account, (string) $data['pin']);

                if ($check['status'] === 'locked') {
                    $lockedApproval->update([
                        'status' => 'rejected',
                        'processed_at' => now(),
                    ]);

                    // Jurnal Audit: percobaan gagal karena PIN terkunci/diblokir.
                    TransactionActivityLogger::gagal($lockedApproval->teller_id, [
                        'type' => $lockedApproval->type,
                        'amount' => $lockedApproval->amount,
                        'account_number' => $account->account_number,
                        'reference_number' => $lockedApproval->reference_number,
                        'nasabah' => $account->user?->name,
                    ], $check['reset_required']
                        ? 'PIN diblokir karena salah 3 kali. Nasabah wajib reset PIN.'
                        : 'PIN terkunci sementara karena percobaan salah berulang.');

                    return response()->json([
                        'message' => $pins->lockedMessage($check['retry_after']),
                        'code' => $check['reset_required'] ? 'PIN_BLOCKED' : 'PIN_LOCKED',
                        'retry_after' => $check['retry_after'],
                    ], 423);
                }

                if ($check['status'] === 'wrong') {
                    // Jurnal Audit: percobaan gagal karena PIN salah.
                    TransactionActivityLogger::gagal($lockedApproval->teller_id, [
                        'type' => $lockedApproval->type,
                        'amount' => $lockedApproval->amount,
                        'account_number' => $account->account_number,
                        'reference_number' => $lockedApproval->reference_number,
                        'nasabah' => $account->user?->name,
                    ], 'PIN salah. Sisa percobaan: ' . $check['attempts_left'] . '.');

                    return response()->json([
                        'message' => 'PIN salah. Sisa percobaan: ' . $check['attempts_left'] . '.',
                        'code' => 'PIN_INVALID',
                        'attempts_left' => $check['attempts_left'],
                    ], 422);
                }

                $account->load('user');
                $teller = $lockedApproval->teller()->firstOrFail();
                $transaction = $posting->post(
                    $account,
                    $teller,
                    $lockedApproval->type,
                    (float) $lockedApproval->amount,
                    $lockedApproval->description,
                    $lockedApproval->reference_number
                );

                $lockedApproval->update([
                    'status' => 'approved',
                    'transaction_id' => $transaction->id,
                    'processed_at' => now(),
                ]);

                // Jejak audit "transaksi berhasil" ditulis di TransactionPostingService
                // (satu tempat) supaya tidak tercatat ganda.

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
            $saldoGagal = DB::transaction(function () use ($approval) {
                $lockedApproval = CustomerTransactionApproval::whereKey($approval->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedApproval?->status === 'pending') {
                    $lockedApproval->update([
                        'status' => 'rejected',
                        'processed_at' => now(),
                    ]);

                    return $lockedApproval;
                }

                return null;
            });

            // Jurnal Audit: transaksi gagal saat posting (mis. saldo mengendap
            // tidak cukup) beserta alasannya.
            if ($saldoGagal) {
                $saldoGagal->loadMissing('account.user');

                TransactionActivityLogger::gagal($saldoGagal->teller_id, [
                    'type' => $saldoGagal->type,
                    'amount' => $saldoGagal->amount,
                    'account_number' => $saldoGagal->account?->account_number,
                    'reference_number' => $saldoGagal->reference_number,
                    'nasabah' => $saldoGagal->account?->user?->name,
                ], $exception->getMessage());
            }

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

            // Jurnal Audit: nasabah menolak permintaan transaksi.
            TransactionActivityLogger::gagal($lockedApproval->teller_id, [
                'type' => $lockedApproval->type,
                'amount' => $lockedApproval->amount,
                'account_number' => $account->account_number,
                'reference_number' => $lockedApproval->reference_number,
                'nasabah' => $account->user?->name,
            ], 'Ditolak nasabah melalui aplikasi. Saldo tidak berubah.');

            return response()->json([
                'message' => 'Permintaan transaksi ditolak. Saldo tidak berubah.',
                'code' => 'TRANSACTION_REJECTED_BY_CUSTOMER',
            ]);
        });
    }
}
