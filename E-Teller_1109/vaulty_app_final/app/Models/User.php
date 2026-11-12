<?php
/* Tujuan file: Model ini merepresentasikan data user di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use App\Models\Account;
use App\Models\CustomerNotification;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;


/*
 * must_change_password & password_changed_at sengaja TIDAK dimasukkan ke Fillable
 * supaya tidak bisa diisi lewat request. Ubah lewat forceFill() di kode server.
 */
#[Fillable([
    'name', 'username', 'password', 'role', 'status', 'email', 'alamat',
    'nis', 'kelas', 'jurusan', 'tingkat', 'kelas_urut', 'angkatan',
    'nip', 'jabatan', 'photo',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Jendela aktivasi akun nasabah baru (hari), dihitung sejak akun dibuat. */
    public const ACTIVATION_WINDOW_DAYS = 60;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'activation_deadline' => 'datetime',
        ];
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(CustomerNotification::class);
    }

    /**
     * Akun nasabah baru dibuat berstatus NONAKTIF dan baru menjadi aktif
     * setelah nasabah menyelesaikan aktivasi di aplikasi mobile. Selama
     * must_change_password masih true, aktivasi belum pernah dilakukan.
     */
    public function isAwaitingActivation(): bool
    {
        return $this->role === 'customer' && (bool) $this->must_change_password;
    }

    /**
     * Tenggat aktivasi (60 hari) sudah lewat.
     *
     * Akun TIDAK dihapus dan TIDAK dikunci permanen: statusnya tetap nonaktif
     * selama belum ada aktivasi, lalu menjadi aktif begitu nasabah
     * menyelesaikan aktivasi di aplikasi mobile.
     */
    public function activationDeadlinePassed(): bool
    {
        return $this->isAwaitingActivation()
            && $this->activation_deadline !== null
            && $this->activation_deadline->isPast();
    }

    /**
     * Sisa hari aktivasi. Null bila akun tidak sedang menunggu aktivasi.
     */
    public function activationDaysLeft(): ?int
    {
        if (!$this->isAwaitingActivation() || $this->activation_deadline === null) {
            return null;
        }

        $today = now()->startOfDay();
        $deadline = $this->activation_deadline->copy()->startOfDay();

        if ($deadline->lessThan($today)) {
            return 0;
        }

        return (int) $today->diffInDays($deadline);
    }

    /**
     * Label status yang ditampilkan pada halaman Manajemen Pengguna.
     */
    public function statusLabel(): string
    {
        if ($this->isAwaitingActivation() && $this->status !== 'blocked') {
            return $this->activationDeadlinePassed()
                ? 'Nonaktif (tenggat aktivasi berakhir)'
                : 'Menunggu aktivasi';
        }

        return $this->status === 'active' ? 'Aktif' : 'Nonaktif';
    }
}
