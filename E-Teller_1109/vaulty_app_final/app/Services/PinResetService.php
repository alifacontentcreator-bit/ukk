<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CredentialResetRequest;
use App\Models\CustomerNotification;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Alur reset PIN dua orang (maker-checker):
 *   Teller memeriksa identitas & mengajukan  ->  Admin menyetujui  ->  Nasabah mengganti PIN sementara.
 * Semua jalur reset (termasuk reset darurat admin) lewat service ini,
 * sehingga SELALU tercatat di tabel credential_reset_requests.
 *
 * Method yang gagal karena aturan bisnis melempar DomainException dengan pesan
 * siap tampil; controller cukup menangkapnya.
 */
class PinResetService
{
    public const EXPIRE_HOURS = 24;

    /** Tandai pengajuan pending yang lewat batas waktu sebagai kedaluwarsa. */
    public function expireStale(): void
    {
        CredentialResetRequest::where('status', CredentialResetRequest::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => CredentialResetRequest::STATUS_EXPIRED]);
    }

    /**
     * Teller mengajukan reset PIN. Belum mengubah apa pun pada PIN nasabah.
     */
    public function request(User $teller, string $accountNumber, string $reason, string $verificationNote): CredentialResetRequest
    {
        return DB::transaction(function () use ($teller, $accountNumber, $reason, $verificationNote) {
            $account = Account::where('account_number', $accountNumber)->lockForUpdate()->first();

            if (!$account) {
                throw new DomainException('Nomor rekening tidak ditemukan.');
            }

            $account->load('user');

            if ($account->status !== 'active' || !$account->user || $account->user->status !== 'active') {
                throw new DomainException('Rekening/akun nasabah sedang tidak aktif. Reset PIN tidak dapat diajukan.');
            }

            if ($account->pin_is_default) {
                throw new DomainException('Nasabah belum membuat PIN. Minta nasabah membuat PIN melalui aplikasi.');
            }

            $this->expireStale();

            $alreadyOpen = CredentialResetRequest::open()
                ->where('account_id', $account->id)
                ->where('type', CredentialResetRequest::TYPE_PIN)
                ->exists();

            if ($alreadyOpen) {
                throw new DomainException('Sudah ada pengajuan reset PIN untuk rekening ini yang masih menunggu Admin.');
            }

            return CredentialResetRequest::create([
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'type' => CredentialResetRequest::TYPE_PIN,
                'requested_by' => $teller->id,
                'reason' => $reason,
                'verification_note' => $verificationNote,
                'status' => CredentialResetRequest::STATUS_PENDING,
                'expires_at' => now()->addHours(self::EXPIRE_HOURS),
            ]);
        });
    }

    /**
    * Admin menyetujui. Mengembalikan true bila PIN berhasil direset,
     * false bila pengajuan ternyata sudah kedaluwarsa (ditandai kedaluwarsa).
     */
    /**
     * Nasabah mengajukan sendiri reset PIN dari aplikasi mobile.
     * Admin tetap wajib memverifikasi identitas sebelum menyetujui.
     */
    public function requestByCustomer(User $customer, string $nis, ?string $reason = null): CredentialResetRequest
    {
        return DB::transaction(function () use ($customer, $nis, $reason) {
            $account = Account::where('user_id', $customer->id)->lockForUpdate()->first();

            if (!$account) {
                throw new DomainException('Data rekening tidak ditemukan.');
            }

            $identifiers = array_filter([
                $customer->username,
                $customer->nis,
                $customer->nisn,
            ]);

            if (!in_array($nis, $identifiers, true)) {
                throw new DomainException('NIS tidak sesuai dengan akun yang sedang login.');
            }

            if ($customer->status !== 'active' || ($account->status !== 'active' && !$account->pin_blocked)) {
                throw new DomainException('Akun atau rekening Anda sedang tidak aktif. Hubungi Administrator.');
            }

            $this->expireStale();

            $alreadyOpen = CredentialResetRequest::open()
                ->where('account_id', $account->id)
                ->where('type', CredentialResetRequest::TYPE_PIN)
                ->exists();

            if ($alreadyOpen) {
                throw new DomainException('Pengajuan lupa PIN Anda masih menunggu persetujuan Admin.');
            }

            $blocksAccount = (bool) $account->pin_blocked;
            $note = trim((string) $reason);

            return CredentialResetRequest::create([
                'account_id' => $account->id,
                'user_id' => $customer->id,
                'type' => CredentialResetRequest::TYPE_PIN,
                'requested_by' => $customer->id,
                'reason' => $note !== ''
                    ? $note
                    : ($blocksAccount
                        ? 'Nasabah terkunci setelah 3 kali salah PIN dan mengajukan buka blokir + reset PIN.'
                        : 'Nasabah lupa PIN dan mengajukan reset melalui aplikasi.'),
                'verification_note' => $blocksAccount
                    ? 'Pengajuan BUKA BLOKIR + reset PIN dari aplikasi. Administrator wajib memverifikasi identitas nasabah.'
                    : 'Diajukan sendiri oleh nasabah melalui aplikasi. Administrator wajib memverifikasi identitas sebelum menyetujui.',
                'status' => CredentialResetRequest::STATUS_PENDING,
                'expires_at' => now()->addHours(self::EXPIRE_HOURS),
            ]);
        });
    }

    public function approve(CredentialResetRequest $request, User $reviewer): bool
    {
        return DB::transaction(function () use ($request, $reviewer) {
            $req = CredentialResetRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (!$req->isPending()) {
                throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
            }

            if ($req->type !== CredentialResetRequest::TYPE_PIN) {
                throw new DomainException('Jenis pengajuan ini belum didukung.');
            }

            if ($req->isExpired()) {
                $req->update(['status' => CredentialResetRequest::STATUS_EXPIRED]);

                return false;
            }

            // Pemeriksa harus orang lain: pembuat pengajuan tidak boleh menyetujui sendiri.
            if ((int) $req->requested_by === (int) $reviewer->id) {
                throw new DomainException('Pengajuan tidak boleh disetujui oleh orang yang membuatnya.');
            }

            $account = Account::whereKey($req->account_id)->lockForUpdate()->firstOrFail();

            $this->applyReset($account);

            $req->update([
                'status' => CredentialResetRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return true;
        });
    }

    public function reject(CredentialResetRequest $request, User $reviewer, string $note): void
    {
        DB::transaction(function () use ($request, $reviewer, $note) {
            $req = CredentialResetRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (!$req->isPending()) {
                throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $req->update([
                'status' => CredentialResetRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
        });
    }

    /** Teller membatalkan pengajuannya sendiri selama belum diproses. */
    public function cancel(CredentialResetRequest $request, User $teller): void
    {
        DB::transaction(function () use ($request, $teller) {
            $req = CredentialResetRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ((int) $req->requested_by !== (int) $teller->id) {
                throw new DomainException('Anda hanya dapat membatalkan pengajuan milik Anda sendiri.');
            }

            if (!$req->isPending()) {
                throw new DomainException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $req->update([
                'status' => CredentialResetRequest::STATUS_CANCELLED,
                'reviewed_at' => now(),
                'review_note' => 'Dibatalkan oleh teller.',
            ]);
        });
    }

    /**
    * Reset darurat oleh admin langsung. Tetap wajib alasan dan tercatat sebagai
    * pengajuan berstatus approved, serta membatalkan pengajuan pending terbuka.
     */
    public function forceReset(Account $account, User $admin, string $reason): CredentialResetRequest
    {
        return DB::transaction(function () use ($account, $admin, $reason) {
            $account = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            CredentialResetRequest::open()
                ->where('account_id', $account->id)
                ->where('type', CredentialResetRequest::TYPE_PIN)
                ->update([
                    'status' => CredentialResetRequest::STATUS_CANCELLED,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'review_note' => 'Digantikan reset darurat oleh Administrator.',
                ]);

            $this->applyReset($account);

            return CredentialResetRequest::create([
                'account_id' => $account->id,
                'user_id' => $account->user_id,
                'type' => CredentialResetRequest::TYPE_PIN,
                'requested_by' => $admin->id,
                'reason' => $reason,
                'verification_note' => 'Reset darurat langsung oleh Administrator.',
                'status' => CredentialResetRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'expires_at' => now(),
            ]);
        });
    }

    /**
     * Tandai PIN perlu dibuat ulang, bersihkan hitungan salah/kunci, dan beri tahu nasabah.
     * Penjagaan "wajib membuat PIN" dilakukan di server lewat pin_is_default = true
     * (middleware pin.ready di API + penolakan penarikan di form teller).
     */
    private function applyReset(Account $account): void
    {
        // Blokir karena PIN salah 3x ikut dibuka. Blokir manual oleh Admin
        // (pin_blocked = false) tidak diubah oleh persetujuan reset PIN.
        $wasPinBlocked = (bool) $account->pin_blocked;
        $restoreActive = $wasPinBlocked && $account->status === 'blocked';

        $account->update([
            'pin' => Hash::make('123456'),
            'pin_is_default' => true,
            'pin_attempts' => 0,
            'pin_locked_until' => null,
            'pin_reset_required' => false,
            'pin_blocked' => false,
            'status' => $restoreActive ? 'active' : $account->status,
        ]);

        CustomerNotification::create([
            'user_id' => $account->user_id,
            'title' => 'PIN rekening direset',
            'message' => 'PIN Anda berhasil diubah oleh Administrator pada '
                . now()->format('d/m/Y H:i')
                . '. PIN sementara Anda adalah 123456. Segera buat PIN baru melalui aplikasi. Jika Anda tidak merasa mengajukan reset, hubungi teller.',
            'type' => 'security',
        ]);
    }
}