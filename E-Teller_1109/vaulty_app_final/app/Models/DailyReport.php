<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'pecahan_10k',
        'pecahan_5k',
        'pecahan_2k',
        'pecahan_1k',
        'pecahan_500',
        'pecahan_100',
        'pecahan_50',

        // Rincian lembaran saldo awal (modal yang diterima Teller)
        'pecahan_awal_100k',
        'pecahan_awal_50k',
        'pecahan_awal_20k',
        'pecahan_awal_10k',
        'pecahan_awal_5k',
        'pecahan_awal_2k',
        'pecahan_awal_1k',
        'pecahan_awal_500',
        'pecahan_awal_100',
        'pecahan_awal_50',

        // Rekap transaksi
        'deposit_total',
        'withdrawal_total',
        'transaction_count',
        'expected_cash',

        // Catatan Teller atas selisih kas (wajib bila uang fisik != kas seharusnya)
        'catatan_selisih',

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
        'pecahan_10k' => 'integer',
        'pecahan_5k' => 'integer',
        'pecahan_2k' => 'integer',
        'pecahan_1k' => 'integer',
        'pecahan_500' => 'integer',
        'pecahan_100' => 'integer',
        'pecahan_50' => 'integer',

        'pecahan_awal_100k' => 'integer',
        'pecahan_awal_50k' => 'integer',
        'pecahan_awal_20k' => 'integer',
        'pecahan_awal_10k' => 'integer',
        'pecahan_awal_5k' => 'integer',
        'pecahan_awal_2k' => 'integer',
        'pecahan_awal_1k' => 'integer',
        'pecahan_awal_500' => 'integer',
        'pecahan_awal_100' => 'integer',
        'pecahan_awal_50' => 'integer',

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

    /**
     * Transaksi hari itu yang dipilih Teller sebagai penjelasan selisih kas.
     *
     * Dipakai ketika uang fisik di laci berbeda dengan kas akhir sistem,
     * mis. pada hari yang hanya berisi penarikan tunai sehingga uang di
     * laci lebih kecil daripada modal yang diterima Teller.
     */
    public function transaksiPenjelas(): BelongsToMany
    {
        return $this->belongsToMany(
            Transaction::class,
            'daily_report_transactions',
            'daily_report_id',
            'transaction_id'
        )->withTimestamps();
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
     * Daftar pecahan uang fisik kas akhir yang dipakai pada formulir &
     * cetakan. Urut dari nominal terbesar ke terkecil; uang kertas
     * memakai satuan "lembar", uang logam memakai satuan "keping".
     *
     * @var array<int, array{nominal: int, kolom: string, satuan: string}>
     */
    public const PECAHAN_FISIK = [
        ['nominal' => 100000, 'kolom' => 'pecahan_100k', 'satuan' => 'lembar'],
        ['nominal' => 50000, 'kolom' => 'pecahan_50k', 'satuan' => 'lembar'],
        ['nominal' => 20000, 'kolom' => 'pecahan_20k', 'satuan' => 'lembar'],
        ['nominal' => 10000, 'kolom' => 'pecahan_10k', 'satuan' => 'lembar'],
        ['nominal' => 5000, 'kolom' => 'pecahan_5k', 'satuan' => 'lembar'],
        ['nominal' => 2000, 'kolom' => 'pecahan_2k', 'satuan' => 'lembar'],
        ['nominal' => 1000, 'kolom' => 'pecahan_1k', 'satuan' => 'lembar'],
        ['nominal' => 500, 'kolom' => 'pecahan_500', 'satuan' => 'keping'],
        ['nominal' => 100, 'kolom' => 'pecahan_100', 'satuan' => 'keping'],
        ['nominal' => 50, 'kolom' => 'pecahan_50', 'satuan' => 'keping'],
    ];

    /**
     * Daftar pecahan rincian lembaran saldo awal (modal diterima).
     *
     * Formulir Teller tidak lagi meminta rincian ini (cukup satu rincian
     * lembaran: uang fisik kas akhir). Daftar tetap dipakai pada cetakan
     * rekapitulasi yang dikirim ke Supervisor agar laporan lama yang
     * sudah merinci modal tetap tampil lengkap.
     *
     * @var array<int, array{nominal: int, kolom: string, satuan: string}>
     */
    public const PECAHAN_AWAL = [
        ['nominal' => 100000, 'kolom' => 'pecahan_awal_100k', 'satuan' => 'lembar'],
        ['nominal' => 50000, 'kolom' => 'pecahan_awal_50k', 'satuan' => 'lembar'],
        ['nominal' => 20000, 'kolom' => 'pecahan_awal_20k', 'satuan' => 'lembar'],
        ['nominal' => 10000, 'kolom' => 'pecahan_awal_10k', 'satuan' => 'lembar'],
        ['nominal' => 5000, 'kolom' => 'pecahan_awal_5k', 'satuan' => 'lembar'],
        ['nominal' => 2000, 'kolom' => 'pecahan_awal_2k', 'satuan' => 'lembar'],
        ['nominal' => 1000, 'kolom' => 'pecahan_awal_1k', 'satuan' => 'lembar'],
        ['nominal' => 500, 'kolom' => 'pecahan_awal_500', 'satuan' => 'keping'],
        ['nominal' => 100, 'kolom' => 'pecahan_awal_100', 'satuan' => 'keping'],
        ['nominal' => 50, 'kolom' => 'pecahan_awal_50', 'satuan' => 'keping'],
    ];

    /**
     * Pecahan utama yang wajib dihitung Teller saat menutup kas.
     *
     * @var array<int, string>
     */
    public const PECAHAN_WAJIB = ['pecahan_100k', 'pecahan_50k', 'pecahan_20k'];

    /**
     * Total uang fisik di laci menurut hitungan Teller.
     */
    public function totalFisik(): float
    {
        return $this->hitungPecahan(self::PECAHAN_FISIK);
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
     * Perubahan kas bersih hari itu: setoran - penarikan.
     *
     * Hanya keterangan arus kas, bukan selisih kas fisik.
     */
    public function perubahanKasBersih(): float
    {
        return (float) $this->deposit_total - (float) $this->withdrawal_total;
    }

    /**
     * Keterangan otomatis perubahan kas bersih.
     */
    public function keteranganPerubahanKas(): string
    {
        $setoran = (float) $this->deposit_total;
        $penarikan = (float) $this->withdrawal_total;

        if ($setoran <= 0 && $penarikan > 0) {
            return 'Kas berkurang karena hanya ada tarik tunai, tidak ada setoran';
        }

        if (abs($setoran - $penarikan) < 0.01) {
            return 'Kas tetap';
        }

        return $setoran > $penarikan ? 'Kas bertambah' : 'Kas berkurang';
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
     * Rincian lembaran saldo awal untuk cetakan rekapitulasi.
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
