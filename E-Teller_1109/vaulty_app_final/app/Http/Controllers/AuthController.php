<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use App\Models\User;

class AuthController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $requestKey = $request->session()->get('recovery_request_key');
        $recovery = $requestKey
            ? DB::table('password_recovery_requests')
                ->join('users', 'users.id', '=', 'password_recovery_requests.user_id')
                ->where('password_recovery_requests.request_key_hash', hash('sha256', $requestKey))
                ->select('password_recovery_requests.*', 'users.name', 'users.role')
                ->first()
            : null;

        if ($recovery && Carbon::parse($recovery->expires_at)->isPast() && !in_array($recovery->status, ['completed', 'rejected', 'email_failed'], true)) {
            DB::table('password_recovery_requests')->where('id', $recovery->id)->update(['status' => 'expired', 'updated_at' => now()]);
            $recovery = null;
            $request->session()->forget('recovery_request_key');
        }

        return view('auth.lupa-sandi', compact('recovery'));
    }

    public function requestPasswordRecovery(Request $request)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:teller,supervisor'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'nis' => ['required_if:role,teller', 'nullable', 'string', 'max:30'],
            'kelas' => ['required_if:role,teller', 'nullable', 'string', 'max:30'],
            'nip' => ['required_if:role,supervisor', 'nullable', 'string', 'max:30'],
            'alamat' => ['required_if:role,supervisor', 'nullable', 'string', 'max:255'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $limitKey = 'password-recovery-request:' . $request->ip();

        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return back()->withErrors(['email' => 'Terlalu banyak permintaan. Coba lagi dalam beberapa menit.'])->withInput();
        }
        RateLimiter::hit($limitKey, 300);

        $query = User::whereRaw('LOWER(email) = ?', [$email])
            ->where('role', $validated['role'])
            ->where('status', 'active')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($validated['name']))]);

        if ($validated['role'] === 'teller') {
            $query->whereRaw('LOWER(TRIM(nis)) = ?', [mb_strtolower(trim($validated['nis']))])
                ->whereRaw('LOWER(TRIM(kelas)) = ?', [mb_strtolower(trim($validated['kelas']))]);
        } else {
            $query->whereRaw('LOWER(TRIM(nip)) = ?', [mb_strtolower(trim($validated['nip']))])
                ->whereRaw('LOWER(TRIM(alamat)) = ?', [mb_strtolower(trim($validated['alamat']))]);
        }

        $user = $query->first();

        if ($user) {
            DB::table('password_recovery_requests')
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'sending', 'email_failed'])
                ->update(['status' => 'replaced', 'updated_at' => now()]);

            $requestKey = bin2hex(random_bytes(32));
            DB::table('password_recovery_requests')->insert([
                'user_id' => $user->id,
                // Simpan email kanonik dari data akun (bukan hasil ketikan) supaya
                // tautan atur ulang sandi pasti dikirim ke email yang terdaftar Admin.
                'email' => mb_strtolower(trim($user->email)),
                'request_key_hash' => hash('sha256', $requestKey),
                'status' => 'pending',
                'expires_at' => now()->addMinutes(30),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $request->session()->put('recovery_request_key', $requestKey);
        } else {
            $request->session()->forget('recovery_request_key');
        }

        return redirect()->route('password.forgot')->with('recovery_submitted', 'Jika identitas sesuai, permintaan Anda sedang menunggu persetujuan Administrator.');
    }

    public function showPasswordResetForm(string $token)
    {
        $recovery = DB::table('password_recovery_requests')
            ->where('reset_token_hash', hash('sha256', $token))
            ->where('status', 'link_sent')
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($recovery, 404, 'Tautan reset tidak valid atau sudah kedaluwarsa.');

        return view('auth.reset-sandi', ['token' => $token, 'email' => $recovery->email]);
    }

    public function resetPassword(Request $request, string $token)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $tokenHash = hash('sha256', $token);

        $reset = DB::transaction(function () use ($tokenHash, $validated) {
            $recovery = DB::table('password_recovery_requests')
                ->where('reset_token_hash', $tokenHash)
                ->where('status', 'link_sent')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$recovery) {
                return false;
            }

            $user = User::whereKey($recovery->user_id)
                ->whereIn('role', ['teller', 'supervisor'])
                ->where('status', 'active')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($recovery->email)])
                ->lockForUpdate()
                ->first();

            if (!$user) {
                return false;
            }

            $user->forceFill(['password' => $validated['password']])->save();
            DB::table('password_recovery_requests')->where('id', $recovery->id)->update([
                'status' => 'completed',
                'reset_token_hash' => null,
                'updated_at' => now(),
            ]);

            return true;
        });

        if (!$reset) {
            return redirect()->route('password.forgot')->withErrors(['email' => 'Tautan sudah dipakai, kedaluwarsa, atau akun tidak aktif. Ajukan permintaan baru.']);
        }

        return redirect()->route('login')->with('success', 'Sandi berhasil diubah. Silakan masuk menggunakan sandi baru.');
    }

    public function authenticate(Request $request)
    {
        // 1. Validasi Input form harus diisi
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 2. PENGECEKAN GEMBOK ASLI (Mencocokkan Username & Password di Database)
        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // 3. Keamanan Tambahan: Tolak login jika akun berstatus 'blocked' (Diblokir)
            if ($user->status === 'blocked') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'username' => 'Akses ditolak. Akun Anda sedang diblokir. Hubungi Administrator.',
                ]);
            }

            // 4. Buat sesi login baru agar aman dari serangan Hacker (Session Fixation)
            $request->session()->regenerate();

            // 5. Arahkan ke pintu yang benar sesuai Jabatan (Role)
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'teller') {
                return redirect()->route('teller.dashboard');
            } elseif ($user->role === 'supervisor') {
                return redirect()->route('supervisor.dashboard');
            } else {
                // Nasabah (Customer)
                return redirect()->route('customer.dashboard');
            }
        }

        // Jika kombinasi Username & Password tidak cocok
        return back()->withErrors([
            'username' => 'Nama pengguna atau sandi yang Anda masukkan salah.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}