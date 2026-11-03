<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang setelah auth:sanctum. Token bisa hidup lebih lama dari status akun,
 * jadi status nasabah & rekening dicek ulang di SETIAP request.
 */
class EnsureActiveCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'customer') {
            return response()->json([
                'message' => 'Akun ini tidak memiliki akses ke aplikasi nasabah.',
                'code' => 'FORBIDDEN_ROLE',
            ], 403);
        }

        $account = $user->account;

        if ($user->status !== 'active' || !$account || $account->status !== 'active') {
            // Cabut token yang sedang dipakai supaya perangkat ini harus login ulang.
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'message' => 'Akun atau rekening Anda sedang tidak aktif. Hubungi Administrator.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        // Agar AuditObserver (yang memakai Auth::id()) mencatat siapa pelakunya
        // pada request API, set user ke guard default.
        Auth::setUser($user);

        return $next($request);
    }
}