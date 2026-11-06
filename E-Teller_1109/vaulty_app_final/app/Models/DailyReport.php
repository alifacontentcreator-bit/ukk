<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    protected $fillable = [
        'teller_id',
        'report_date',

        // Kas awal dan rincian uang fisik
        'saldo_awal',
        'pecahan_100k',
        'pecahan_50k',
        'pecahan_20k',
        'pecahan_5k',

        // Rincian lembaran saldo awal (modal yang diterima Teller)
        'pecahan_awal_100k',
        'pecahan_awal_50k',
        'pecahan_awal_20k',
        'pecahan_awal_5k',

        // Rekap transaksi
        'deposit_total',
        'withdrawal_total',
        'transaction_count',
        'expected_cash',

        // Status laporan
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'rejected_by',
        'rejected_at',

        // Status pemindahan kas
        'transfer_status',
        'transferred_by',
        'transferred_at',

        // Riwayat pengiriman laporan
        'submitted_at',

        // Riwayat pembukaan kembali sesi
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    protected $casts = [
        'report_date' => 'date',

        'saldo_awal' => 'decimal:2',

        'pecahan_100k' => 'integer',
        'pecahan_50k' => 'integer',
        'pecahan_20k' => 'integer',
        'pecahan_5k' => 'integer',

        'pecahan_awal_100k' => 'integer',
        'pecahan_awal_50k' => 'integer',
        'pecahan_awal_20k' => 'integer',
        'pecahan_awal_5k' => 'integer',

        'deposit_total' => 'decimal:2',
        'withdrawal_total' => 'decimal:2',
        'expected_cash' => 'decimal:2',

        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'transferred_at' => 'datetime',

        'submitted_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    // Relasi dengan Teller
    public function teller()
    {
        return $this->belongsTo(User::class, 'teller_id');
    }

    // Relasi Supervisor yang menyetujui laporan
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Relasi Supervisor yang menolak laporan
    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // Notifikasi keputusan Supervisor untuk Teller
    public function notifications(): HasMany
    {
        return $this->hasMany(ReportNotification::class);
    }

    // Relasi Supervisor yang menerima kas
    public function transferApprover()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    // Relasi Supervisor yang membuka kembali sesi
    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    // Riwayat pembukaan kembali laporan
    public function reopenLogs()
    {
        return $this->hasMany(DailyReportReopenLog::class);
    }

    /**
     * Daftar pecahan uang fisik kas akhir yang dipakai pada cetakan.
     *
     * @var array<int, array{nominal: int, kolom: string, satuan: string}>
     */
    public const PECAHAN_FISIK = [
        ['nominal' => 100000, 'kolom' => 'pecahan_100k', 'satuan' => 'lembar'],
        ['nominal' => 50000, 'kolom' => 'pecahan_50k', 'satuan' => 'lembar'],
        ['nominal' => 20000, 'kolom' => 'pecahan_20k', 'satuan' => 'lembar'],
        ['nominal' => 5000, 'kolom' => 'pecahan_5k', 'satuan' => 'lembar'],
    ];

    /**
     * Daftar pecahan rincian lembaran saldo awal (modal diterima).
     *
     * @var array<int, array{nominal: int, kolom: string, satuan: string}>
     */
    public const PECAHAN_AWAL = [
        ['nominal' => 100000, 'kolom' => 'pecahan_awal_100k', 'satuan' => 'lembar'],
        ['nominal' => 50000, 'kolom' => 'pecahan_awal_50k', 'satuan' => 'lembar'],
        ['nominal' => 20000, 'kolom' => 'pecahan_awal_20k', 'satuan' => 'lembar'],
        ['nominal' => 5000, 'kolom' => 'pecahan_awal_5k', 'satuan' => 'lembar'],
    ];

    /**
     * Total uang fisik di laci menurut hitungan Teller.
     */
    public function totalFisik(): float
    {
        return $this->hitungPecahan(self::PECAHAN_FISIK);
    }

    /**
     * Total lembaran saldo awal yang dihitung Teller (0 bila tidak diisi).
     */
    public function totalFisikAwal(): float
    {
        return $this->hitungPecahan(self::PECAHAN_AWAL);
    }

    /**
     * Kas akhir menurut sistem: saldo awal + setoran - penarikan - pengembalian.
     */
    public function saldoAkhirSistem(): float
    {
        return (float) $this->saldo_awal + (float) $this->expected_cash;
    }

    /**
     * Selisih kas: positif berarti kas lebih, negatif berarti kas kurang.
     */
    public function selisihKas(): float
    {
        return $this->totalFisik() - $this->saldoAkhirSistem();
    }

    /**
     * Baris rincian lembaran untuk cetakan (hanya pecahan yang terisi).
     *
     * @param  array<int, array{nominal: int, kolom: string, satuan: string}>  $daftar
     * @return array<int, array{nominal: int, jumlah: int, total: float, satuan: string}>
     */
    public function rincianPecahan(array $daftar): array
    {
        $baris = [];

        foreach ($daftar as $pecahan) {
            $jumlah = (int) ($this->{$pecahan['kolom']} ?? 0);

            if ($jumlah <= 0) {
                continue;
            }

            $baris[] = [
                'nominal' => $pecahan['nominal'],
                'jumlah' => $jumlah,
                'total' => $jumlah * $pecahan['nominal'],
                'satuan' => $pecahan['satuan'],
            ];
        }

        return $baris;
    }

    /**
     * Rincian lembaran kas akhir.
     *
     * @return array<int, array{nominal: int, jumlah: int, total: float, satuan: string}>
     */
    public function rincianFisik(): array
    {
        return $this->rincianPecahan(self::PECAHAN_FISIK);
    }

    /**
     * Rincian lembaran saldo awal.
     *
     * @return array<int, array{nominal: int, jumlah: int, total: float, satuan: string}>
     */
    public function rincianFisikAwal(): array
    {
        return $this->rincianPecahan(self::PECAHAN_AWAL);
    }

    /**
     * @param  array<int, array{nominal: int, kolom: string, satuan: string}>  $daftar
     */
    private function hitungPecahan(array $daftar): float
    {
        $total = 0.0;

        foreach ($daftar as $pecahan) {
            $total += (int) ($this->{$pecahan['kolom']} ?? 0) * $pecahan['nominal'];
        }

        return $total;
    }
}
