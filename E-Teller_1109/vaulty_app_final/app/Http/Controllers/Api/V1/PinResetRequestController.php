<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur pin reset request controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CredentialResetRequest;
use App\Services\PinResetService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lupa PIN dari aplikasi nasabah.
 *
 * Nasabah memasukkan NIS lalu mengajukan reset PIN. Pengajuan muncul di
 * halaman Admin "Reset PIN" untuk disetujui/ditolak. Setelah disetujui,
 * account.pin_is_default menjadi true sehingga aplikasi otomatis meminta
 * nasabah membuat PIN baru.
 */
class PinResetRequestController extends Controller
{
    public function store(Request $request, PinResetService $resets): JsonResponse
    {
        $data = $request->validate([
            'nis' => ['required', 'digits:5'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'nis.required' => 'NIS wajib diisi.',
            'nis.digits' => 'NIS harus terdiri dari 5 angka.',
        ]);

        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'message' => 'Data rekening tidak ditemukan.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $identifiers = array_filter([$user->username, $user->nis, $user->nisn]);

        if (!in_array($data['nis'], $identifiers, true)) {
            return response()->json([
                'message' => 'NIS tidak sesuai dengan akun yang sedang login.',
                'code' => 'NIS_NOT_MATCH',
            ], 422);
        }

        if ($account->pin_is_default) {
            return response()->json([
                'message' => 'PIN belum pernah dibuat. Silakan buat PIN baru langsung di aplikasi.',
                'code' => 'PIN_SETUP_AVAILABLE',
            ], 409);
        }

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($account->pin_blocked && $reason === '') {
            return response()->json([
                'message' => 'Alasan wajib diisi untuk pengajuan buka blokir.',
                'code' => 'REASON_REQUIRED',
            ], 422);
        }

        $alreadyOpen = CredentialResetRequest::open()
            ->where('account_id', $account->id)
            ->where('type', CredentialResetRequest::TYPE_PIN)
            ->exists();

        if ($alreadyOpen) {
            return response()->json([
                'message' => 'Pengajuan lupa PIN Anda masih menunggu persetujuan Admin.',
                'code' => 'PIN_RESET_PENDING',
            ], 409);
        }

        try {
            $pinRequest = $resets->requestByCustomer($user, $data['nis'], $reason ?: null);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'PIN_RESET_REQUEST_REJECTED',
            ], 422);
        }

        return response()->json([
            'message' => 'Pengajuan lupa PIN dikirim ke Administrator. Tunggu '
                . 'persetujuan; aplikasi akan meminta PIN baru setelah disetujui.',
            'code' => 'PIN_RESET_REQUEST_SENT',
            'data' => [
                'id' => $pinRequest->id,
                'status' => $pinRequest->status,
                'requested_at' => $pinRequest->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function status(Request $request, PinResetService $resets): JsonResponse
    {
        $resets->expireStale();

        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'message' => 'Data rekening tidak ditemukan.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        $latest = CredentialResetRequest::where('account_id', $account->id)
            ->where('type', CredentialResetRequest::TYPE_PIN)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => [
                'id' => $latest?->id,
                'status' => $latest?->status ?? 'none',
                'requested_at' => $latest?->created_at?->toIso8601String(),
                'reviewed_at' => $latest?->reviewed_at?->toIso8601String(),
                'review_note' => $latest?->review_note,
                'pin_setup_required' => (bool) $account->pin_is_default,
                'pin_reset_required' => (bool) $account->pin_reset_required,
                'pin_blocked' => (bool) $account->pin_blocked,
            ],
        ]);
    }
}
