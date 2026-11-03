<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Hash;

/**
 * Satu-satunya tempat logika PIN, dipakai bersama oleh:
 * - otorisasi transaksi nasabah (API mobile)
 * - ganti PIN nasabah (API mobile)
 * - nanti: persetujuan reset PIN
 *
 * Panggil verify() di dalam DB::transaction dengan akun yang sudah
 * di-lockForUpdate(), supaya dua request bersamaan tidak bisa
 * "mencuri" percobaan PIN.
 */
class PinService
{
    public const MAX_ATTEMPTS = 5;

    public function isLocked(Account $account): bool
    {
        return $account->pin_reset_required
            || ($account->pin_locked_until !== null && $account->pin_locked_until->isFuture());
    }

    public function secondsLeft(Account $account): int
    {
        if (!$this->isLocked($account)) {
            return 0;
        }

        if ($account->pin_reset_required) {
            return 0;
        }

        return max(1, $account->pin_locked_until->getTimestamp() - now()->getTimestamp());
    }

    /**
     * Cek PIN dan catat percobaannya.
     *
     * @return array{status: 'ok'}|array{status: 'wrong', attempts_left: int}|array{status: 'locked', retry_after: int, reset_required: bool}
     */
    public function verify(Account $account, string $pin): array
    {
        if ($this->isLocked($account)) {
            return [
                'status' => 'locked',
                'retry_after' => $this->secondsLeft($account),
                'reset_required' => (bool) $account->pin_reset_required,
            ];
        }

        if (Hash::check($pin, $account->pin)) {
            if ($account->pin_attempts > 0 || $account->pin_locked_until !== null) {
                $account->update(['pin_attempts' => 0, 'pin_locked_until' => null]);
            }

            return ['status' => 'ok'];
        }

        $attempts = $account->pin_attempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $account->update([
                'pin_attempts' => self::MAX_ATTEMPTS,
                'pin_locked_until' => null,
                'pin_reset_required' => true,
            ]);

            return [
                'status' => 'locked',
                'retry_after' => 0,
                'reset_required' => true,
            ];
        }

        $account->update(['pin_attempts' => $attempts]);

        return ['status' => 'wrong', 'attempts_left' => self::MAX_ATTEMPTS - $attempts];
    }

    /**
     * Alasan PIN baru ditolak, atau null kalau PIN-nya boleh dipakai.
     */
    public function weaknessReason(string $pin): ?string
    {
        if (!preg_match('/^\d{6}$/', $pin)) {
            return 'PIN harus 6 digit angka.';
        }

        if (preg_match('/^(\d)\1{5}$/', $pin)) {
            return 'PIN tidak boleh angka kembar semua (contoh: 111111).';
        }

        if (str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            return 'PIN tidak boleh berurutan (contoh: 234567).';
        }

        return null;
    }

    /**
     * Pesan siap tampil bila PIN terkunci.
     */
    public function lockedMessage(int $retryAfterSeconds): string
    {
        if ($retryAfterSeconds === 0) {
            return 'PIN terkunci setelah 5 kali salah. Nasabah harus mengajukan reset PIN kepada Teller.';
        }

        $minutes = (int) ceil($retryAfterSeconds / 60);

        return "PIN nasabah terkunci sementara karena terlalu banyak salah. Coba lagi dalam {$minutes} menit, atau ajukan reset PIN.";
    }
}