<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur profile controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileEmailRequest;
use App\Http\Requests\UpdateProfilePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

/**
 * Halaman "Profil Saya" untuk SEMUA peran.
 *
 * Aturan keamanan yang dijaga di controller ini:
 * - Selalu memakai pengguna yang sedang login (auth user); tidak ada ID di URL.
 * - Hanya kolom name / email / password yang diambil dari request (whitelist).
 * - Ubah email & ubah sandi wajib menyertakan sandi saat ini (Hash::check).
 * - Setelah sandi berubah, sesi lain milik pengguna tersebut diakhiri.
 * - Tidak pernah menampilkan/mencatat nilai sandi.
 */
class ProfileController extends Controller
{
    /**
     * Peran yang memakai email untuk PEMULIHAN SANDI.
     *
     * Sumber: AuthController@requestPasswordRecovery (role in: teller, supervisor)
     * dan AdminPasswordRecoveryController@approve (role in: teller, supervisor).
     * Nasabah memakai alur PIN di aplikasi, jadi tidak diberi bagian email.
     */
    private const EMAIL_RECOVERY_ROLES = ['teller', 'supervisor'];

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'layout' => $this->layoutFor((string) $user->role),
            'roleLabel' => $this->roleLabel((string) $user->role),
            'canEditName' => $user->role !== 'customer',
            'usesRecoveryEmail' => in_array($user->role, self::EMAIL_RECOVERY_ROLES, true),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'customer') {
            return redirect()->route('profile.show')
                ->withErrors(['name' => 'Nama resmi nasabah hanya dapat diubah oleh Administrator.']);
        }

        // Whitelist: hanya "name" yang dipakai, field lain di request diabaikan.
        $newName = trim((string) $request->validated()['name']);
        $oldName = (string) $user->name;

        if ($newName === $oldName) {
            return redirect()->route('profile.show')
                ->with('success', 'Tidak ada perubahan nama yang perlu disimpan.');
        }

        // saveQuietly(): audit dicatat manual (satu catatan per aksi, lebih jelas).
        $user->forceFill(['name' => $newName])->saveQuietly();

        $this->logAudit(
            'profile.name.updated',
            'Mengubah nama profil sendiri dari "' . $oldName . '" menjadi "' . $newName . '".'
        );

        return redirect()->route('profile.show')
            ->with('success', 'Nama lengkap berhasil diperbarui.');
    }

    public function updateEmail(UpdateProfileEmailRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (!in_array($user->role, self::EMAIL_RECOVERY_ROLES, true)) {
            return redirect()->route('profile.show')
                ->withErrors(['email' => 'Email pemulihan tidak dipakai pada akun ini. Hubungi Administrator untuk perubahan data akun.']);
        }

        if (!Hash::check((string) $request->input('current_password'), $user->getAuthPassword())) {
            return redirect()->route('profile.show')
                ->withErrors(['current_password' => 'Sandi saat ini tidak sesuai.']);
        }

        // Huruf kecil + trim supaya sesuai data yang dipakai alur pemulihan sandi.
        $newEmail = mb_strtolower(trim((string) $request->validated()['email']));
        $oldEmail = mb_strtolower(trim((string) $user->email));

        if ($newEmail === $oldEmail) {
            return redirect()->route('profile.show')
                ->with('success', 'Tidak ada perubahan email yang perlu disimpan.');
        }

        $user->forceFill(['email' => $newEmail])->saveQuietly();

        $this->logAudit(
            'profile.email.updated',
            'Mengubah email pemulihan dari "' . ($oldEmail === '' ? '(kosong)' : $oldEmail) . '" menjadi "' . $newEmail . '".'
        );

        return redirect()->route('profile.show')
            ->with('success', 'Email pemulihan berhasil diperbarui.');
    }

    public function updatePassword(UpdateProfilePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $newPassword = (string) $request->input('password');

        if (!Hash::check((string) $request->input('current_password'), $user->getAuthPassword())) {
            return redirect()->route('profile.show')
                ->withErrors(['current_password' => 'Sandi saat ini tidak sesuai.']);
        }

        // Tolak bila sandi baru sama dengan sandi lama (walau kolom "sandi saat ini" salah ketik).
        if (Hash::check($newPassword, $user->getAuthPassword())) {
            return redirect()->route('profile.show')
                ->withErrors(['password' => 'Sandi baru tidak boleh sama dengan sandi lama.']);
        }

        // Cast "hashed" pada model User yang melakukan Hash::make.
        $user->forceFill([
            'password' => $newPassword,
            'password_changed_at' => now(),
        ])->saveQuietly();

        // Sesi saat ini diperbarui, sesi lain milik pengguna ini diakhiri.
        $request->session()->regenerate();
        $this->endOtherSessions($request, (int) $user->getKey());

        // Catatan audit TANPA nilai sandi.
        $this->logAudit('profile.password.updated', 'Mengubah sandi akun sendiri.');

        return redirect()->route('profile.show')
            ->with('success', 'Sandi berhasil diperbarui. Sesi lain pada akun ini telah diakhiri.');
    }

    /**
     * Akhiri sesi login lain milik pengguna yang sama.
     *
     * Aplikasi memakai SESSION_DRIVER=database (tanpa middleware
     * AuthenticateSession), jadi cara yang cocok adalah menghapus baris sesi
     * pengguna tersebut selain sesi yang sedang dipakai.
     */
    private function endOtherSessions(Request $request, int $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        try {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Catat perubahan profil ke tabel audit_logs yang sudah ada.
     *
     * Hanya kolom fillable milik model AuditLog yang diisi (user_id, action,
     * description) agar cocok dengan skema yang dipakai AuditObserver.
     * Tidak pernah memuat nilai sandi/hash/PIN.
     */
    private function logAudit(string $action, string $description): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'description' => $description,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function layoutFor(string $role): string
    {
        return match ($role) {
            'admin' => 'layouts.admin',
            'supervisor' => 'layouts.supervisor',
            'teller' => 'layouts.teller',
            default => 'layouts.app',
        };
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrator',
            'supervisor' => 'Supervisor',
            'teller' => 'Teller',
            default => 'Nasabah',
        };
    }
}
