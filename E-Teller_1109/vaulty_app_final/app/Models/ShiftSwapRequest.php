<?php
/* Tujuan file: Model ini merepresentasikan data shift swap request di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * =============================================================================
 * FILE INI UNTUK: Data pengajuan TUKAR SHIFT jaga antar Teller.
 * =============================================================================
 *
 * requester_id  = Teller A (yang berhalangan / minta izin).
 * substitute_id = Teller B (yang menggantikan).
 * duty_date     = tanggal shift yang ditukar.
 *
 * Status:
 * - pending   : menunggu keputusan Supervisor.
 * - approved  : disetujui → kunci transaksi pindah dari A ke B pada tanggal itu.
 * - rejected  : ditolak Supervisor.
 * - cancelled : dibatalkan Teller A.
 */
class ShiftSwapRequest extends Model
{
    /** Pengajuan paling cepat H+2 (2 hari setelah hari ini). */
    public const MIN_HARI_DARI_SEKARANG = 2;

    /** Rentang tanggal yang boleh dipilih (contoh hari ini tgl 8 → bisa pilih 10 s.d. 17). */
    public const JENDELA_HARI = 8;

    /** Teller hanya boleh mengajukan tukar shift 1 kali dalam 7 hari. */
    public const JEDA_MINIMAL_HARI = 7;

    public const STATUS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'requester_id',
        'substitute_id',
        'duty_date',
        'duty_shift_id',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'duty_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(DutyShift::class, 'duty_shift_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Hanya pengajuan yang sudah disetujui (dipakai untuk memindahkan hak jaga). */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Daftar tanggal yang boleh dipilih Teller untuk tukar shift:
     * H+2 sampai H+9, dan hanya hari kerja (Senin-Jumat).
     *
     * @return array<int, \Carbon\Carbon>
     */
    public static function tanggalYangBolehDipilih(?CarbonInterface $hariIni = null): array
    {
        $mulai = ($hariIni ?? today())->copy()->startOfDay();
        $tanggal = [];

        for ($i = self::MIN_HARI_DARI_SEKARANG; $i < self::MIN_HARI_DARI_SEKARANG + self::JENDELA_HARI; $i++) {
            $kandidat = $mulai->copy()->addDays($i);

            // Jadwal jaga hanya Senin-Jumat (Sabtu/Minggu tidak ada shift).
            if ($kandidat->dayOfWeekIso <= DutyWeeklySchedule::HARI_TERAKHIR) {
                $tanggal[] = $kandidat;
            }
        }

        return $tanggal;
    }
}
