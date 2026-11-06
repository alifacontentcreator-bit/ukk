<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification;
use App\Models\User;
use App\Services\ConfiguredOtpMailer;
use App\Support\CustomerPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;
    private const TOKEN_DAYS = 30;
    private const ACTIVATION_CODE_MINUTES = 3;

    /** Jeda minimal antar permintaan kode untuk NIS yang sama. */
    private const ACTIVATION_CODE_COOLDOWN_SECONDS = 15;

    /** Masa berlaku kode OTP lupa password. */
    private const PASSWORD_RESET_CODE_MINUTES = 3;

    /** Jeda minimal antar permintaan kode OTP lupa password. */
    private const PASSWORD_RESET_COOLDOWN_SECONDS = 15;

    /**
     * Mengirim kode aktivasi ke email yang sudah terdaftar pada akun nasabah.
     */
    public function requestActivationCode(
        Request $request,
        ConfiguredOtpMailer $mailer
    ): JsonResponse {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
        ], [
            'username.required' => 'NIS wajib diisi.',
        ]);

        $rateLimitKey = 'activation-code:'
            . hash('sha256', Str::lower($data['username']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json([
                'message' => 'Tunggu '
                    . self::ACTIVATION_CODE_COOLDOWN_SECONDS
                    . ' detik sebelum meminta kode lagi.',
                'code' => 'ACTIVATION_CODE_RATE_LIMITED',
                'retry_after' => RateLimiter::availableIn($rateLimitKey),
            ], 429);
        }

        RateLimiter::hit(
            $rateLimitKey,
            self::ACTIVATION_CODE_COOLDOWN_SECONDS
        );

        $activationCode = Str::upper(Str::random(8));

        $result = DB::transaction(function () use ($data, $activationCode) {
            // NIS tersimpan pada kolom username, NISN pada kolom nisn, dan
            // sebagian data lama menyimpan NIS pada kolom nis. Ketiganya
            // dicari agar nasabah bisa memakai nomor yang mereka hafal.
            $user = User::where('role', 'customer')
                ->where(function ($query) use ($data) {
                    $query->where('username', $data['username'])
                        ->orWhere('nis', $data['username'])
                        ->orWhere('nisn', $data['username']);
                })
                ->lockForUpdate()
                ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'NIS '
                        . $data['username']
                        . ' tidak ditemukan. Periksa kembali nomor yang Anda masukkan.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            if (!$user->must_change_password) {
                return response()->json([
                    'message' => 'Akun ini sudah diaktivasi. Silakan login dengan password Anda.',
                    'code' => 'ALREADY_ACTIVATED',
                ], 409);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'message' => 'Akun Anda sedang tidak aktif. Hubungi Administrator.',
                    'code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }

            if (!filled($user->email)) {
                return response()->json([
                    'message' => 'Akun '
                        . $user->username
                        . ' belum memiliki email terdaftar. Minta Administrator '
                        . 'mendaftarkan email Anda, lalu minta kode lagi.',
                    'code' => 'EMAIL_NOT_REGISTERED',
                ], 422);
            }

            // Kode lama sengaja TIDAK dicabut: setiap kode tetap berlaku
            // sampai kedaluwarsa (3 menit) atau dipakai. Dengan begitu
            // nasabah yang membaca email lama tetap bisa berhasil aktivasi
            // walau sempat menekan "kirim ulang" beberapa kali.
            $tokenId = DB::table('customer_activation_tokens')->insertGetId([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $activationCode),
                'expires_at' => now()->addMinutes(self::ACTIVATION_CODE_MINUTES),
                'used_at' => null,
                'revoked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $tokenId,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $activation = $result;

        try {
            $mailer->sendCustomerActivationCode(
                $activation['email'],
                $activation['name'],
                $activationCode
            );
        } catch (Throwable $exception) {
            DB::table('customer_activation_tokens')
                ->where('id', $activation['id'])
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::error('Customer activation email delivery failed.', [
                'user_id' => $activation['user_id'],
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Email kode belum dapat dikirim. Coba lagi nanti atau hubungi Administrator.',
                'code' => 'ACTIVATION_EMAIL_UNAVAILABLE',
            ], 503);
        }

        $maskedEmail = $this->maskEmail($activation['email']);

        return response()->json([
            'message' => 'Kode aktivasi sudah dikirim ke '
                . $maskedEmail
                . '. Kode berlaku '
                . self::ACTIVATION_CODE_MINUTES
                . ' menit.',
            'code' => 'ACTIVATION_CODE_SENT',
            'email' => $maskedEmail,
            'expires_in' => self::ACTIVATION_CODE_MINUTES * 60,
            'resend_after' => self::ACTIVATION_CODE_COOLDOWN_SECONDS,
        ], 202);
    }

    /**
     * Mengirim kode OTP lupa password ke email yang terdaftar pada akun
     * nasabah. Nasabah hanya perlu memasukkan NIS (5 angka).
     */
    public function requestPasswordResetCode(
        Request $request,
        ConfiguredOtpMailer $mailer
    ): JsonResponse {
        $data = $request->validate([
            'username' => ['required', 'digits:5'],
        ], [
            'username.required' => 'NIS wajib diisi.',
            'username.digits' => 'NIS harus terdiri dari 5 angka.',
        ]);

        $rateLimitKey = 'password-reset-code:'
            . hash('sha256', Str::lower($data['username']) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json([
                'message' => 'Tunggu '
                    . self::PASSWORD_RESET_COOLDOWN_SECONDS
                    . ' detik sebelum meminta kode lagi.',
                'code' => 'PASSWORD_RESET_CODE_RATE_LIMITED',
                'retry_after' => RateLimiter::availableIn($rateLimitKey),
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, self::PASSWORD_RESET_COOLDOWN_SECONDS);

        $resetCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $result = DB::transaction(function () use ($data, $resetCode) {
            $user = User::where('role', 'customer')
                ->where(function ($query) use ($data) {
                    $query->where('username', $data['username'])
                        ->orWhere('nis', $data['username'])
                        ->orWhere('nisn', $data['username']);
                })
                ->lockForUpdate()
                ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'NIS ' . $data['username']
                        . ' tidak ditemukan. Periksa kembali nomor yang Anda masukkan.',
                    'code' => 'USER_NOT_FOUND',
                ], 404);
            }

            if ($user->must_change_password) {
                return response()->json([
                    'message' => 'Akun belum diaktivasi. Gunakan menu aktivasi akun terlebih dahulu.',
                    'code' => 'ACCOUNT_NOT_ACTIVATED',
                ], 409);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'message' => 'Akun Anda sedang tidak aktif. Hubungi Administrator.',
                    'code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }

            if (!filled($user->email)) {
                return response()->json([
                    'message' => 'Akun ' . $user->username
                        . ' belum memiliki email terdaftar. Minta Administrator '
                        . 'mendaftarkan email Anda, lalu minta kode lagi.',
                    'code' => 'EMAIL_NOT_REGISTERED',
                ], 422);
            }

            // Kode lama tidak dicabut agar kode dari email sebelumnya tetap
            // bisa dipakai sampai kedaluwarsa (sama seperti aktivasi).
            $tokenId = DB::table('customer_password_reset_tokens')->insertGetId([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $resetCode),
                'expires_at' => now()->addMinutes(self::PASSWORD_RESET_CODE_MINUTES),
                'used_at' => null,
                'revoked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'id' => $tokenId,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $reset = $result;

        try {
            $mailer->sendCustomerPasswordResetCode(
                $reset['email'],
                $reset['name'],
                $resetCode
            );
        } catch (Throwable $exception) {
            DB::table('customer_password_reset_tokens')
                ->where('id', $reset['id'])
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::error('Customer password reset email delivery failed.', [
                'user_id' => $reset['user_id'],
                'exception' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Email kode OTP belum dapat dikirim. Coba lagi nanti atau hubungi Administrator.',
                'code' => 'PASSWORD_RESET_EMAIL_UNAVAILABLE',
            ], 503);
        }

        $maskedEmail = $this->maskEmail($reset['email']);

        return response()->json([
            'message' => 'Kode OTP sudah dikirim ke '
                . $maskedEmail
                . '. Kode berlaku '
                . self::PASSWORD_RESET_CODE_MINUTES
                . ' menit.',
            'code' => 'PASSWORD_RESET_CODE_SENT',
            'email' => $maskedEmail,
            'expires_in' => self::PASSWORD_RESET_CODE_MINUTES * 60,
            'resend_after' => self::PASSWORD_RESET_COOLDOWN_SECONDS,
        ], 202);
    }

    /**
     * Mengatur ulang password nasabah memakai kode OTP dari email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'digits:5'],
            'reset_code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'username.required' => 'NIS wajib diisi.',
            'username.digits' => 'NIS harus terdiri dari 5 angka.',
            'reset_code.required' => 'Kode OTP wajib diisi.',
            'reset_code.digits' => 'Kode OTP harus terdiri dari 6 angka.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        return DB::transaction(function () use ($data) {
            $user = User::where('role', 'customer')
                ->where(function ($query) use ($data) {
                    $query->where('username', $data['username'])
                        ->orWhere('nis', $data['username'])
                        ->orWhere('nisn', $data['username']);
                })
                ->lockForUpdate()
                ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'NIS atau kode OTP tidak valid.',
                    'code' => 'PASSWORD_RESET_CODE_INVALID',
                ], 422);
            }

            if ($user->must_change_password) {
                return response()->json([
                    'message' => 'Akun belum diaktivasi. Gunakan menu aktivasi akun terlebih dahulu.',
                    'code' => 'ACCOUNT_NOT_ACTIVATED',
                ], 409);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'message' => 'Akun Anda sedang tidak aktif. Hubungi Administrator.',
                    'code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }

            $resetToken = DB::table('customer_password_reset_tokens')
                ->where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $data['reset_code']))
                ->lockForUpdate()
                ->first();

            if (!$resetToken || $resetToken->used_at !== null || $resetToken->revoked_at !== null) {
                return response()->json([
                    'message' => 'Kode OTP tidak sesuai atau sudah digunakan. Minta kode baru melalui aplikasi.',
                    'code' => 'PASSWORD_RESET_CODE_INVALID',
                ], 422);
            }

            if (now()->greaterThanOrEqualTo($resetToken->expires_at)) {
                return response()->json([
                    'message' => 'Kode OTP sudah kedaluwarsa. Minta kode baru melalui aplikasi.',
                    'code' => 'PASSWORD_RESET_CODE_EXPIRED',
                ], 422);
            }

            // Model User memakai cast password => hashed.
            $user->forceFill([
                'password' => $data['password'],
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();

            DB::table('customer_password_reset_tokens')
                ->where('id', $resetToken->id)
                ->update(['used_at' => now(), 'updated_at' => now()]);

            // Kode lain untuk akun ini tidak berlaku lagi.
            DB::table('customer_password_reset_tokens')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where('id', '!=', $resetToken->id)
                ->update(['revoked_at' => now(), 'updated_at' => now()]);

            // Perangkat yang masih login dengan password lama harus login ulang.
            $user->tokens()->delete();

            CustomerNotification::create([
                'user_id' => $user->id,
                'title' => 'Password berhasil diubah',
                'message' => 'Password akun Anda baru saja diubah melalui kode OTP email pada '
                    . now()->format('d/m/Y H:i')
                    . '. Bila Anda tidak melakukannya, segera hubungi Administrator.',
                'type' => 'security',
            ]);

            return response()->json([
                'message' => 'Password berhasil diubah. Silakan login dengan password baru.',
                'code' => 'PASSWORD_RESET_SUCCESS',
            ], 200);
        });
    }

    /**
     * Menyamarkan alamat email agar hanya pemilik akun yang tahu alamat
     * lengkapnya, mis. `irfancahyowibowo267@gmail.com` menjadi
     * `ir***************67@gmail.com`.
     */
    private function maskEmail(string $email): string
    {
        $atPosition = strrpos($email, '@');

        if ($atPosition === false) {
            return $email;
        }

        $localPart = substr($email, 0, $atPosition);
        $domain = substr($email, $atPosition);
        $length = strlen($localPart);

        if ($length <= 3) {
            return substr($localPart, 0, 1)
                . str_repeat('*', max(1, $length - 1))
                . $domain;
        }

        return substr($localPart, 0, 2)
            . str_repeat('*', max(1, $length - 4))
            . substr($localPart, -2)
            . $domain;
    }

    /**
     * Aktivasi akun nasabah melalui aplikasi mobile.
     *
     * Nasabah memasukkan NIS, kode dari email,
     * password baru, dan konfirmasi password.
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'activation_code' => ['required', 'string', 'size:8'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'username.required' => 'NIS wajib diisi.',
            'activation_code.required' => 'Kode aktivasi wajib diisi.',
            'activation_code.size' => 'Kode aktivasi harus terdiri dari 8 karakter.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $result = DB::transaction(function () use ($data) {

            // Cari dan kunci data nasabah (username, NIS, atau NISN).
            $user = User::where(function ($query) use ($data) {
                $query->where('username', $data['username'])
                    ->orWhere('nis', $data['username'])
                    ->orWhere('nisn', $data['username']);
            })
                ->lockForUpdate()
                ->first();

            if (!$user || $user->role !== 'customer') {
                return response()->json([
                    'message' => 'NIS atau kode aktivasi tidak valid.',
                    'code' => 'INVALID_ACTIVATION',
                ], 422);
            }

            // Pastikan akun belum pernah diaktivasi.
            if (!$user->must_change_password) {
                return response()->json([
                    'message' => 'Akun ini sudah diaktivasi. Silakan login.',
                    'code' => 'ALREADY_ACTIVATED',
                ], 409);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'message' => 'Akun Anda sedang tidak aktif. Hubungi Administrator.',
                    'code' => 'ACCOUNT_INACTIVE',
                ], 403);
            }

            // Kode dibuat dalam huruf kapital agar konsisten.
            $activationCode = strtoupper(trim($data['activation_code']));

            // Cari kode aktivasi berdasarkan hash.
            $activationToken = DB::table('customer_activation_tokens')
                ->where('user_id', $user->id)
                ->where('token_hash', hash('sha256', $activationCode))
                ->lockForUpdate()
                ->first();

            if (!$activationToken) {
                return response()->json([
                    'message' => 'Kode aktivasi tidak sesuai. Gunakan kode dari '
                        . 'email terbaru (kode hanya berlaku '
                        . self::ACTIVATION_CODE_MINUTES
                        . ' menit).',
                    'code' => 'ACTIVATION_CODE_INVALID',
                ], 422);
            }

            // Pastikan kode belum digunakan atau dicabut.
            if (
                $activationToken->used_at !== null ||
                $activationToken->revoked_at !== null
            ) {
                return response()->json([
                    'message' => 'Kode aktivasi sudah digunakan atau tidak berlaku.',
                    'code' => 'ACTIVATION_CODE_UNAVAILABLE',
                ], 422);
            }

            // Pastikan kode belum kedaluwarsa.
            $createdAt = \Illuminate\Support\Carbon::parse(
                $activationToken->created_at
            );

            if (
                now()->greaterThanOrEqualTo($activationToken->expires_at) ||
                now()->greaterThanOrEqualTo(
                    $createdAt->addMinutes(self::ACTIVATION_CODE_MINUTES)
                )
            ) {
                return response()->json([
                    'message' => 'Kode aktivasi sudah kedaluwarsa. Minta kode baru melalui aplikasi.',
                    'code' => 'ACTIVATION_CODE_EXPIRED',
                ], 422);
            }

            // Simpan password baru.
            // Model User menggunakan cast password => hashed.
            $user->forceFill([
                'password' => $data['password'],
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();

            // Tandai kode aktivasi sebagai sudah digunakan.
            DB::table('customer_activation_tokens')
                ->where('id', $activationToken->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            // Cabut kode aktivasi lain yang masih aktif.
            DB::table('customer_activation_tokens')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where('id', '!=', $activationToken->id)
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Aktivasi akun berhasil. Silakan login menggunakan password baru.',
                'code' => 'ACTIVATION_SUCCESS',
            ], 200);
        });

        return $result;
    }

    /**
     * Login khusus nasabah melalui aplikasi mobile.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $key = 'api-login:'
            . Str::lower($data['username'])
            . '|'
            . $request->ip();

        // Batasi percobaan login.
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json([
                'message' => 'Terlalu banyak percobaan login. Coba lagi beberapa saat lagi.',
                'code' => 'TOO_MANY_ATTEMPTS',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        // Login memakai NIS atau NISN (tersimpan pada username, nis, atau nisn).
        $user = User::where('username', $data['username'])
            ->orWhere('nis', $data['username'])
            ->orWhere('nisn', $data['username'])
            ->first();

        if ($user) {
            $passwordOk = Hash::check(
                $data['password'],
                $user->password
            );
        } else {
            // Menyamakan proses ketika username tidak ditemukan.
            Hash::make($data['password']);
            $passwordOk = false;
        }

        // Hanya nasabah yang boleh login melalui APK.
        if (!$passwordOk || !$user || $user->role !== 'customer') {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json([
                'message' => 'NIS atau password salah.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        // Nasabah wajib melakukan aktivasi terlebih dahulu.
        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Akun belum diaktivasi. Silakan aktivasi akun terlebih dahulu.',
                'code' => 'ACCOUNT_NOT_ACTIVATED',
            ], 403);
        }

        $account = $user->account;

        // Periksa status akun dan rekening. Rekening yang diblokir karena PIN
        // salah 3x tetap boleh login agar nasabah dapat mengajukan buka blokir.
        if (
            $user->status !== 'active' ||
            !$account ||
            ($account->status !== 'active' && !$account->pin_blocked)
        ) {
            return response()->json([
                'message' => 'Akun atau rekening Anda sedang tidak aktif. Hubungi Administrator.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        RateLimiter::clear($key);

        // Hapus token lama dengan nama perangkat yang sama.
        $user->tokens()
            ->where('name', $data['device_name'])
            ->delete();

        // Buat token baru dengan masa berlaku 30 hari.
        $expiresAt = now()->addDays(self::TOKEN_DAYS);

        $newToken = $user->createToken(
            $data['device_name'],
            ['customer'],
            $expiresAt
        );

        return response()->json(array_merge([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ], CustomerPayload::make($user, $account)));
    }

    /**
     * Logout dan hapus token perangkat yang sedang digunakan.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Berhasil keluar.',
        ]);
    }

    /**
     * Mengambil data nasabah yang sedang login.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'message' => 'Data rekening tidak ditemukan.',
            ], 404);
        }

        if (
            $user->status !== 'active' ||
            $account->status !== 'active'
        ) {
            return response()->json([
                'message' => 'Akun atau rekening Anda sedang tidak aktif.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        return response()->json(
            CustomerPayload::make($user, $account)
        );
    }
}