<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur me controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CustomerPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Profil (baca saja) + saldo terbaru + flag wajib ganti PIN/password.
     * Username, email, dan data kelas tidak bisa diubah dari sini.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (! $account) {
            return response()->json([
                'message' => 'Akun ini belum memiliki rekening Bank Mini.',
                'code' => 'ACCOUNT_NOT_FOUND',
            ], 404);
        }

        return response()->json(CustomerPayload::make($user, $account));
    }
}