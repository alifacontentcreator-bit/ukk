<?php
/* Tujuan file: Middleware ini memeriksa aturan akses atau kondisi permintaan sebelum fitur ensure pin changed diteruskan ke controller. */


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipasang setelah customer.active. Selama nasabah belum membuat PIN sendiri,
 * endpoint yang dibungkus middleware ini menolak dengan code PIN_CHANGE_REQUIRED.
 * Ini penjagaan di SERVER; pop up di aplikasi hanya tampilan.
 */
class EnsurePinChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user()?->account;

        if ($account && $account->pin_is_default) {
            return response()->json([
                'message' => 'Anda harus membuat PIN transaksi terlebih dahulu.',
                'code' => 'PIN_CHANGE_REQUIRED',
            ], 403);
        }

        if ($account && $account->pin_blocked) {
            return response()->json([
                'message' => 'Akun diblokir karena PIN salah 3 kali. Ajukan buka blokir dan reset PIN melalui aplikasi.',
                'code' => 'PIN_BLOCKED',
            ], 423);
        }

        if ($account && $account->pin_reset_required) {
            return response()->json([
                'message' => 'PIN terkunci setelah 3 kali salah. Ajukan buka blokir dan reset PIN melalui aplikasi.',
                'code' => 'PIN_RESET_REQUIRED',
            ], 423);
        }

        return $next($request);
    }
}