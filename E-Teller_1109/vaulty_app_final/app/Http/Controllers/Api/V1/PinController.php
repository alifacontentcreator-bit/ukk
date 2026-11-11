<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur pin controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerNotification;
use App\Models\User;
use App\Services\PinService;
use App\Support\CustomerPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PinController extends Controller
{
    /**
     * Buat PIN pertama kali atau setelah pengajuan reset disetujui.
     * PIN lama tidak diperlukan karena akun hanya diizinkan masuk ke status ini
     * saat rekening dibuat atau petugas menyetujui reset.
     */
    public function setup(Request $request, PinService $pins): JsonResponse
    {
        $data = $request->validate([
            'new_pin' => ['required', 'digits:6', 'confirmed'],
        ]);
        $user = $request->user();
        $new = (string) $data['new_pin'];

        return DB::transaction(function () use ($pins, $new, $user) {
            $account = Account::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($account->pin_blocked) {
                return response()->json([
                    'message' => 'Akun diblokir karena PIN salah 3 kali. Ajukan buka blokir dan reset PIN melalui aplikasi.',
                    'code' => 'PIN_BLOCKED',
                ], 423);
            }

            if ($account->pin_reset_required) {
                return response()->json([
                    'message' => 'Pengajuan lupa PIN Anda belum disetujui. Tunggu persetujuan Admin.',
                    'code' => 'PIN_RESET_REQUIRED',
                ], 423);
            }

            if (!$account->pin_is_default) {
                return response()->json([
                    'message' => 'PIN sudah dibuat. Gunakan menu Ganti PIN untuk mengubahnya.',
                    'code' => 'PIN_ALREADY_SETUP',
                ], 409);
            }

            $weakness = $pins->weaknessReason($new);
            if ($weakness !== null) {
                return response()->json([
                    'message' => $weakness,
                    'code' => 'PIN_WEAK',
                ], 422);
            }

            $this->savePin($account, $user, $new);

            return response()->json(array_merge(
                ['message' => 'PIN berhasil dibuat.'],
                CustomerPayload::make($user, $account->fresh())
            ));
        });
    }

    /**
     * Ganti PIN. Dipakai untuk:
     * - ganti PIN biasa kapan saja
     * Body: current_pin, new_pin, new_pin_confirmation (semua string 6 digit).
     */
    public function change(Request $request, PinService $pins): JsonResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'digits:6'],
            'new_pin' => ['required', 'digits:6', 'confirmed'],
        ]);

        $current = (string) $data['current_pin'];
        $new = (string) $data['new_pin'];
        $user = $request->user();

        return DB::transaction(function () use ($pins, $current, $new, $user) {
            // Kunci baris rekening: hitungan salah PIN tidak boleh balapan.
            $account = Account::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $check = $pins->verify($account, $current);

            if ($check['status'] === 'locked') {
                return response()->json([
                    'message' => $pins->lockedMessage($check['retry_after']),
                    'code' => $check['reset_required'] ? 'PIN_BLOCKED' : 'PIN_LOCKED',
                    'retry_after' => $check['retry_after'],
                ], 423);
            }

            if ($check['status'] === 'wrong') {
                return response()->json([
                    'message' => 'PIN saat ini salah.',
                    'code' => 'PIN_INVALID',
                    'attempts_left' => $check['attempts_left'],
                ], 422);
            }

            if (hash_equals($current, $new)) {
                return response()->json([
                    'message' => 'PIN baru harus berbeda dari PIN lama.',
                    'code' => 'PIN_WEAK',
                ], 422);
            }

            $weakness = $pins->weaknessReason($new);
            if ($weakness !== null) {
                return response()->json(['message' => $weakness, 'code' => 'PIN_WEAK'], 422);
            }

            $this->savePin($account, $user, $new);

            return response()->json(array_merge(
                ['message' => 'PIN berhasil diubah.'],
                CustomerPayload::make($user, $account->fresh())
            ));
        });
    }

    private function savePin(Account $account, User $user, string $pin): void
    {
        $account->update([
            'pin' => Hash::make($pin),
            'pin_is_default' => false,
            'pin_changed_at' => now(),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
            'pin_reset_required' => false,
            'pin_blocked' => false,
        ]);

        CustomerNotification::create([
            'user_id' => $user->id,
            'title' => 'PIN berhasil diperbarui',
            'message' => 'PIN rekening Anda diperbarui pada ' . now()->format('d/m/Y H:i')
                . '. Jika bukan Anda yang melakukannya, segera hubungi petugas.',
            'type' => 'security',
        ]);
    }
}