<?php

namespace App\Models;

use App\Models\Account;
use App\Models\CustomerNotification;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Data operasional yang membuat akun tidak boleh dihapus permanen.
     *
     * Foreign key pada tabel transaksi, laporan harian, dan koreksi sengaja
     * memakai RESTRICT (bukan cascade) agar jejak audit tidak ikut terhapus.
     * Karena itu penghapusan harus ditolak lebih awal dengan pesan yang bisa
     * dibaca admin, bukan dibiarkan gagal dengan error SQL 1451.
     *
     * @return array<int, string>
     */
    public function deletionBlockers(): array
    {
        $blockers = [];

        if ($this->hasAccountTransactionHistory()) {
            $blockers[] = 'riwayat transaksi rekening';
        }

        $references = [
            ['transactions', ['user_id'], 'transaksi yang dilayani sebagai teller'],
            ['daily_reports', ['teller_id', 'approved_by', 'transferred_by', 'reopened_by'], 'laporan harian kas'],
            ['transaction_corrections', ['teller_id', 'supervisor_id'], 'pengajuan koreksi transaksi'],
            ['customer_transaction_approvals', ['teller_id'], 'permintaan persetujuan transaksi nasabah'],
            ['cash_transfers', ['teller_id', 'created_by', 'confirmed_by'], 'transfer kas antar petugas'],
            ['daily_report_reopen_logs', ['supervisor_id'], 'log pembukaan ulang laporan harian'],
        ];

        foreach ($references as [$table, $columns, $label]) {
            if ($this->hasReferenceIn($table, $columns)) {
                $blockers[] = $label;
            }
        }

        return $blockers;
    }

    /**
     * Apakah rekening milik pengguna ini sudah pernah dipakai bertransaksi.
     */
    private function hasAccountTransactionHistory(): bool
    {
        if (!Schema::hasTable('accounts') || !Schema::hasTable('transactions')) {
            return false;
        }

        return DB::table('accounts')
            ->join('transactions', 'transactions.account_id', '=', 'accounts.id')
            ->where('accounts.user_id', $this->getKey())
            ->exists();
    }

    /**
     * Apakah salah satu kolom pada tabel terkait masih menunjuk ke pengguna ini.
     *
     * @param  array<int, string>  $columns
     */
    private function hasReferenceIn(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)
            ->where(function ($query) use ($columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, $this->getKey());
                }
            })
            ->exists();
    }
}