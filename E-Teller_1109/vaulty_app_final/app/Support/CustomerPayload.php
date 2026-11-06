<?php

namespace App\Support;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Satu tempat untuk bentuk data nasabah yang dikirim ke aplikasi mobile,
 * supaya respons /auth/login dan /me selalu konsisten.
 * Sengaja TIDAK memuat: password, hash PIN, remember_token.
 * qr_token tidak dikirim mentah; aplikasi hanya menerima qr_url (URL scan
 * identitas nasabah) agar QR bisa ditampilkan di layar nasabah.
 */
class CustomerPayload
{
    public static function make(User $user, Account $account): array
    {
        $qrUrl = self::qrUrl($account);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'email' => $user->email,
                'photo' => $user->photo,
                'photo_url' => $user->photo
                    ? asset('storage/' . ltrim($user->photo, '/'))
                    : null,
                'alamat' => $user->alamat,
                'nis' => $user->nis,
                'kelas' => $user->kelas,
                'jurusan' => $user->jurusan,
                'angkatan' => $user->angkatan,
                'nip' => $user->nip,
                'jabatan' => $user->jabatan,
            ],
            'account' => [
                'account_number' => $account->account_number,
                'product_type' => $account->product_type,
                'balance' => (float) $account->balance,
                'status' => $account->status,
                'pin_is_default' => (bool) $account->pin_is_default,
                'pin_reset_required' => (bool) $account->pin_reset_required,
                'pin_blocked' => (bool) $account->pin_blocked,
                // URL yang di-encode menjadi QR identitas nasabah (sama dengan
                // QR yang dicetak admin) agar bisa ditampilkan di aplikasi mobile.
                'qr_url' => $qrUrl,
                'qr_available' => $qrUrl !== null,
            ],
            // Flag untuk layar wajib ganti PIN / password di aplikasi.
            'must_change_pin' => (bool) $account->pin_is_default,
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }

    /**
     * Rekening lama bisa saja belum punya qr_token (mis. dibuat sebelum kolom
     * qr_token diisi otomatis), jadi dipastikan sekali di sini supaya QR nasabah
     * selalu muncul baik di web maupun di aplikasi mobile.
     */
    private static function qrUrl(Account $account): ?string
    {
        if (blank($account->qr_token)) {
            $account->qr_token = Str::random(48);
            $account->save();
        }

        if (blank($account->qr_token)) {
            return null;
        }

        return route('customer.qr-account', $account->qr_token);
    }
}