<?php
/* Tujuan file: Controller ini menangani alur API untuk fitur dashboard controller, termasuk menerima permintaan dan mengembalikan respons sesuai proses bisnisnya. */


namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TargetSaving;
use App\Support\CustomerPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return a compact dashboard for the authenticated customer.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = request()->user();
        $account = $user->account;

        // Basic profile & saldo via existing payload helper
        $profile = CustomerPayload::make($user, $account);

        // Aggregated transaction data
        $totalIncome = Transaction::where('account_id', $account->id)
            ->where('type', 'deposit')
            ->whereIn('status', ['approved', 'success', 'corrected'])
            ->sum('amount');
        $totalExpense = Transaction::where('account_id', $account->id)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['approved', 'success', 'corrected'])
            ->sum('amount');
        $transactionCount = Transaction::where('account_id', $account->id)
            ->whereIn('status', ['approved', 'success', 'corrected'])
            ->count();
        $recentTransactions = Transaction::where('account_id', $account->id)
            ->whereIn('status', ['approved', 'success', 'corrected'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'reference_number', 'type', 'amount', 'created_at', 'description', 'status']);

        // Target saving summary (first active target if any)
        $target = TargetSaving::where('account_id', $account->id)->first();
        $targetData = null;
        if ($target) {
            $saved = $target->saved_amount;
            $goal = $target->target_amount;
            $percentage = $goal > 0 ? round(($saved / $goal) * 100, 2) : 0;
            $targetData = [
                'id' => $target->id,
                'nama' => $target->name,
                'target_amount' => $goal,
                'saved_amount' => $saved,
                'percentage' => $percentage,
                'deadline' => optional($target->deadline)->toDateString(),
            ];
        }

        $data = array_merge(
            $profile,
            [
                'saldo' => $account->balance,
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'transaction_count' => $transactionCount,
                'recent_transactions' => $recentTransactions,
                'target_saving' => $targetData,
                'status' => $account->status,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved',
            'data' => $data,
        ]);
    }
}

