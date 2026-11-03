<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConfiguredOtpMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class AdminPasswordRecoveryController extends Controller
{
    public function index(): View
    {
        $requests = DB::table('password_recovery_requests')
            ->join('users', 'users.id', '=', 'password_recovery_requests.user_id')
            ->whereIn('password_recovery_requests.status', ['pending', 'email_failed'])
            ->where(function ($query) {
                $query->where('password_recovery_requests.status', 'email_failed')
                    ->orWhere('password_recovery_requests.expires_at', '>', now());
            })
            ->select([
                'password_recovery_requests.id',
                'password_recovery_requests.email',
                'password_recovery_requests.created_at',
                'password_recovery_requests.expires_at',
                'password_recovery_requests.status',
                'users.name',
                'users.role',
                'users.nis',
                'users.kelas',
                'users.nip',
                'users.alamat',
            ])
            ->oldest('password_recovery_requests.created_at')
            ->get();

        return view('admin.pemulihan-sandi', compact('requests'));
    }

    public function approve(int $id, ConfiguredOtpMailer $otpMailer): RedirectResponse
    {
        $recovery = DB::table('password_recovery_requests')
            ->where('id', $id)
            ->whereIn('status', ['pending', 'email_failed'])
            ->where(function ($query) {
                $query->where('status', 'email_failed')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$recovery) {
            return back()->withErrors(['recovery' => 'Permintaan tidak ditemukan, sudah diproses, atau sudah kedaluwarsa.']);
        }

        $user = User::whereKey($recovery->user_id)
            ->whereIn('role', ['teller', 'supervisor'])
            ->where('status', 'active')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($recovery->email)])
            ->first();

        if (!$user) {
            DB::table('password_recovery_requests')->where('id', $id)->update(['status' => 'rejected', 'updated_at' => now()]);
            return back()->withErrors(['recovery' => 'Akun sudah tidak memenuhi syarat untuk pemulihan sandi.']);
        }

        $previousStatus = $recovery->status;
        $claimed = DB::table('password_recovery_requests')
            ->where('id', $id)
            ->where('status', $previousStatus)
            ->update(['status' => 'sending', 'updated_at' => now()]);

        if (!$claimed) {
            return back()->withErrors(['recovery' => 'Permintaan sudah diproses Administrator lain.']);
        }

        $resetToken = bin2hex(random_bytes(32));
        $resetUrl = route('password.reset.form', ['token' => $resetToken]);

        try {
            $otpMailer->sendPasswordResetLink($recovery->email, $user->name, $resetUrl);
        } catch (Throwable $exception) {
            report($exception);
            DB::table('password_recovery_requests')->where('id', $id)->where('status', 'sending')->update([
                'status' => 'email_failed',
                'updated_at' => now(),
            ]);
            return back()->withErrors(['recovery' => 'Tautan reset belum terkirim. Periksa konfigurasi SMTP lalu kirim ulang. Sandi pengguna belum berubah.']);
        }

        DB::table('password_recovery_requests')->where('id', $id)->where('status', 'sending')->update([
            'reset_token_hash' => hash('sha256', $resetToken),
            'status' => 'link_sent',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Tautan reset sandi berlaku 30 menit dan telah dikirim ke email akun terkait.');
    }
}
