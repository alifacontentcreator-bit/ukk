<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur balance controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\CustomerPayload;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    /**
     * Return the current balance of the authenticated customer.
     */
    public function show(): JsonResponse
    {
        $user = request()->user();
        $account = $user->account;

        return response()->json([
            'success' => true,
            'message' => 'Saldo berhasil diambil',
            'data'    => [
                'balance' => $account->balance,
                'currency' => 'IDR',
                'account_number' => $account->account_number,
            ],
        ]);
    }
}

