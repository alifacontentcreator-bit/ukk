<?php
/* Tujuan file: Service ini menampung proses bisnis bersama untuk fitur cash drawer service agar dapat dipakai konsisten oleh beberapa bagian aplikasi. */


namespace App\Services;

use App\Models\CashTransfer;
use App\Models\SystemSetting;
use App\Models\Transaction;

class CashDrawerService
{
    /**
     * Key pengaturan saldo awal brankas.
     *
     * Nilai ini adalah modal dasar brankas yang berlaku terus-menerus
     * (tidak disimpan per tanggal dan tidak direset setiap hari).
     */
    public const SETTING_VAULT_INITIAL = 'vault_initial_balance';

    /**
     * Nilai dasar perhitungan saldo brankas.
     *
     * Disimpan saat Supervisor menyimpan saldo awal, yaitu
     * saldo awal + seluruh pergerakan kas yang sudah terjadi.
     * Dengan begitu saldo brankas saat ini langsung sama dengan
     * saldo awal yang baru diisi, lalu berkurang/bertambah hanya
     * karena pergerakan kas berikutnya.
     */
    public const SETTING_VAULT_BASELINE = 'vault_balance_baseline';

    /**
     * Mengambil saldo awal brankas yang ditetapkan Supervisor.
     *
     * Saldo awal ini dipakai terus-menerus sebagai nilai dasar.
     * Nilai 0 / kosong dianggap belum ditetapkan, sehingga data lama
     * (vault_initial_balance_YYYY-MM-DD) tetap dipakai sebagai cadangan
     * agar saldo brankas tidak tiba-tiba menjadi nol atau minus.
     */
    public function vaultInitialBalance(): float
    {
        $value = SystemSetting::where('key', self::SETTING_VAULT_INITIAL)->value('value');

        if ($value === null || (float) $value <= 0) {
            $legacy = $this->legacyVaultInitialBalance();

            if ($legacy !== null && (float) $legacy > 0) {
                $value = $legacy;
            }
        }

        return round((float) $value, 2);
    }

    /**
     * Apakah Supervisor sudah menetapkan saldo awal brankas?
     */
    public function hasVaultInitialBalance(): bool
    {
        return $this->vaultInitialBalance() > 0;
    }

    /**
     * Saldo awal versi lama (per tanggal). Diambil dari tanggal terakhir.
     */
    private function legacyVaultInitialBalance(): ?string
    {
        $rows = SystemSetting::where('key', 'like', self::SETTING_VAULT_INITIAL . '%')
            ->orderByDesc('key')
            ->get(['key', 'value']);

        $legacy = $rows->first(
            fn (SystemSetting $row) => $row->key !== self::SETTING_VAULT_INITIAL
        );

        return $legacy?->value;
    }

    /**
     * Nilai dasar perhitungan saldo brankas saat ini.
     *
     * Bila Supervisor sudah pernah menyimpan saldo awal, nilai ini adalah
     * saldo awal ditambah pergerakan kas yang sudah ada saat itu. Bila belum
     * pernah disimpan, pergerakan kas yang sudah ada dianggap sudah tercermin
     * pada saldo awal sehingga saldo saat ini sama dengan saldo awal.
     */
    public function vaultBaseline(): float
    {
        $baseline = SystemSetting::where('key', self::SETTING_VAULT_BASELINE)->value('value');

        if ($baseline === null) {
            return round(
                $this->vaultInitialBalance() + $this->totalCashOut() - $this->totalCashIn(),
                2
            );
        }

        return round((float) $baseline, 2);
    }

    /**
     * Menyelaraskan nilai dasar dengan saldo awal yang baru disimpan.
     *
     * Pergerakan kas yang sudah terjadi dianggap sudah tercermin pada angka
     * saldo awal yang diisi Supervisor, sehingga saldo brankas saat ini
     * tepat sama dengan angka tersebut.
     */
    public function syncVaultBaseline(float $initialBalance): float
    {
        $baseline = round(
            $initialBalance + $this->totalCashOut() - $this->totalCashIn(),
            2
        );

        SystemSetting::updateOrCreate(
            ['key' => self::SETTING_VAULT_BASELINE],
            ['value' => (string) $baseline]
        );

        return $baseline;
    }

    /**
     * Total modal yang sudah diserahkan ke Teller (pending + confirmed).
     */
    private function totalCashOut(): float
    {
        return (float) CashTransfer::where('type', 'opening')
            ->whereIn('status', ['pending', 'confirmed'])
            ->sum('amount');
    }

    /**
     * Total kas Teller yang sudah dikonfirmasi masuk ke brankas.
     */
    private function totalCashIn(): float
    {
        return (float) CashTransfer::where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');
    }

    /**
     * Menghitung saldo brankas Supervisor secara kumulatif.
     *
     * Saldo brankas = nilai dasar brankas
     *                 - seluruh modal yang diserahkan ke Teller
     *                   (pending maupun confirmed)
     *                 + kas yang sudah dikembalikan Teller ke brankas
     *                   (confirmed).
     *
     * Nilai dasar berasal dari saldo awal yang disimpan Supervisor plus
     * pergerakan kas yang sudah ada saat penyimpanan (lihat syncVaultBaseline()),
     * sehingga saldo saat ini tepat sama dengan saldo awal yang diisi dan baru
     * berubah karena pergerakan kas berikutnya. Bila $date diisi, pergerakan
     * kas dibatasi sampai tanggal tersebut.
     */
    public function supervisorBalance(
        ?float $initialBalance = null,
        ?string $date = null
    ): float {
        $initialBalance = $initialBalance ?? $this->vaultBaseline();

        $modalDiserahkan = CashTransfer::query()
            ->where('type', 'opening')
            ->whereIn('status', ['pending', 'confirmed']);

        // Kas yang sudah benar-benar diterima kembali di brankas.
        $kasKembali = CashTransfer::query()
            ->where('type', 'closing')
            ->where('status', 'confirmed');

        if ($date) {
            $modalDiserahkan->whereDate('transfer_date', '<=', $date);
            $kasKembali->whereDate('transfer_date', '<=', $date);
        }

        return round(
            $initialBalance
                - (float) $modalDiserahkan->sum('amount')
                + (float) $kasKembali->sum('amount'),
            2
        );
    }

    /**
     * Menghitung kas akhir Teller pada tanggal tertentu.
     *
     * Kas akhir = modal diterima + setoran - penarikan
     *             - pengembalian kas ke brankas (confirmed).
     */
    public function tellerBalance(
        int $tellerId,
        ?string $date = null
    ): float {
        $date = $date ?? today()->toDateString();

        $modalDiterima = CashTransfer::where('teller_id', $tellerId)
            ->whereDate('transfer_date', $date)
            ->where('type', 'opening')
            ->where('status', 'confirmed')
            ->sum('amount');

        $setoran = Transaction::where('user_id', $tellerId)
            ->whereDate('created_at', $date)
            ->where('type', 'deposit')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $penarikan = Transaction::where('user_id', $tellerId)
            ->whereDate('created_at', $date)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        // Kas yang sudah diserahkan kembali ke brankas (dikonfirmasi Supervisor).
        $pengembalian = CashTransfer::where('teller_id', $tellerId)
            ->whereDate('transfer_date', $date)
            ->where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');

        return round(
            (float) $modalDiterima
            + (float) $setoran
            - (float) $penarikan
            - (float) $pengembalian,
            2
        );
    }
}