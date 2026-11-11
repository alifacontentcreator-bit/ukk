<?php
/* Tujuan file: Service ini menampung proses bisnis bersama untuk fitur transaction posting service agar dapat dipakai konsisten oleh beberapa bagian aplikasi. */


namespace App\Services;

use App\Models\Account;
use App\Models\AccountingJournal;
use App\Models\CustomerNotification;
use App\Models\DailyReport;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

class TransactionPostingService
{
    /**
     * Mengambil batas minimum saldo rekening nasabah.
     */
    public function minBalance(): int
    {
        return (int) (
            SystemSetting::where('key', 'min_balance')->value('value') ?: 10000
        );
    }

    /**
     * Memastikan laporan harian Teller masih terbuka.
     * Transaksi hanya diperbolehkan saat laporan berstatus draft.
     *
     * Harus dipanggil di dalam DB::transaction().
     */
    public function assertDayOpen(User $teller): ?DailyReport
    {
        $report = DailyReport::where('teller_id', $teller->id)
            ->whereDate('report_date', today())
            ->lockForUpdate()
            ->first();

        if ($report && $report->status !== 'draft') {
            throw new DomainException(
                'Transaksi ditolak. Laporan penutupan kas hari ini sudah dikirim, ditolak, atau disetujui. ' .
                'Hubungi Supervisor jika laporan perlu dibuka kembali.'
            );
        }

        return $report;
    }

    /**
     * Mencatat transaksi dan memperbarui saldo nasabah.
     *
     * Harus dipanggil di dalam DB::transaction().
     * Rekening nasabah harus sudah dikunci menggunakan lockForUpdate().
     */
    public function post(
        Account $account,
        User $teller,
        string $type,
        float $amount,
        ?string $description = null,
        ?string $referenceNumber = null
    ): Transaction {
        // Memastikan laporan harian masih terbuka.
        $report = $this->assertDayOpen($teller);

        // Validasi jenis transaksi.
        if (!in_array($type, ['deposit', 'withdrawal'], true)) {
            throw new DomainException('Jenis transaksi tidak valid.');
        }

        // Setoran minimal Rp5.000; penarikan tidak memiliki batas minimum.
        if (!is_finite($amount) || $amount <= 0 || ($type === 'deposit' && $amount < 5000)) {
            throw new DomainException($type === 'deposit'
                ? 'Nominal setoran minimal Rp 5.000.'
                : 'Nominal penarikan harus lebih besar dari Rp 0.');
        }

        $amount = round($amount, 2);

        /*
         * Mengunci data Teller agar transaksi kas dari Teller
         * yang sama tidak diproses bersamaan.
         */
        User::whereKey($teller->id)
            ->lockForUpdate()
            ->firstOrFail();

        // Mengambil saldo rekening sebelum transaksi.
        $before = (float) $account->balance;

        $isDeposit = $type === 'deposit';

        /*
         * Pemeriksaan saldo kas fisik Teller.
         */
        $cashDrawer = app(CashDrawerService::class);
        $drawerBalance = $cashDrawer->tellerBalance($teller->id);

        if (!$isDeposit && $drawerBalance < $amount) {
            throw new DomainException(
                'Penarikan ditolak. Uang tunai di laci Teller tidak mencukupi. ' .
                'Kas tersedia Rp ' .
                number_format($drawerBalance, 0, ',', '.') . '.'
            );
        }

        /*
         * Proses setoran dan penarikan rekening nasabah.
         */
        if ($isDeposit) {
            $account->balance = $before + $amount;
        } else {
            $min = $this->minBalance();

            if (($before - $amount) < $min) {
                throw new DomainException(
                    'Penarikan ditolak. Saldo rekening wajib tersisa minimal Rp ' .
                    number_format($min, 0, ',', '.') . '.'
                );
            }

            $account->balance = $before - $amount;
        }

        // Simpan saldo rekening terbaru.
        $account->save();

        $after = (float) $account->balance;

        /*
         * Membuat catatan transaksi.
         * Status langsung success karena transaksi diproses
         * dan diselesaikan oleh Teller.
         */
        $transaction = Transaction::create([
            'daily_report_id' => $report?->id,
            'transaction_code' => 'TRX-' . strtoupper(Str::random(10)),
            'reference_number' => $referenceNumber
                ?: 'REF' . now()->format('ymd') . strtoupper(Str::random(6)),
            'account_id' => $account->id,
            'user_id' => $teller->id,
            'type' => $type,
            'amount' => $amount,
            'status' => 'success',
            'description' => $description ?: (
                $isDeposit ? 'Setor Tunai' : 'Tarik Tunai'
            ),
            'data_before' => [
                'balance' => $before,
            ],
            'data_after' => [
                'balance' => $after,
            ],
        ]);

        /*
         * Pencatatan jurnal akuntansi.
         *
         * Kode akun:
         * 111 = Kas
         * 211 = Tabungan Nasabah
         *
         * SETORAN:
         * Debit Kas
         * Kredit Tabungan Nasabah
         *
         * PENARIKAN:
         * Debit Tabungan Nasabah
         * Kredit Kas
         */
        AccountingJournal::create([
            'transaction_id' => $transaction->id,
            'account_code' => $isDeposit ? '111' : '211',
            'position' => 'debit',
            'amount' => $amount,
        ]);

        AccountingJournal::create([
            'transaction_id' => $transaction->id,
            'account_code' => $isDeposit ? '211' : '111',
            'position' => 'credit',
            'amount' => $amount,
        ]);

        /*
         * Membuat notifikasi untuk nasabah.
         */
        CustomerNotification::create([
            'user_id' => $account->user_id,
            'title' => $isDeposit
                ? 'Setoran berhasil'
                : 'Penarikan berhasil',

            'message' => ($isDeposit ? 'Setoran' : 'Penarikan') .
                ' sebesar Rp ' . number_format($amount, 0, ',', '.') .
                ' berhasil. Saldo Anda sekarang Rp ' .
                number_format($after, 0, ',', '.') .
                '. Ref: ' . $transaction->reference_number . '.',

            'type' => 'transaction',
            'transaction_id' => $transaction->id,
        ]);

        /*
         * Jejak audit: transaksi berhasil dicatat untuk Jurnal Audit Supervisor.
         * Diletakkan di choke point posting supaya semua jalur (konfirmasi PIN
         * nasabah maupun koreksi) tercatat tanpa terkecuali.
         */
        TransactionActivityLogger::berhasil($teller->id, [
            'type' => $type,
            'amount' => $amount,
            'account_number' => $account->account_number,
            'reference_number' => $transaction->reference_number,
            'nasabah' => $account->user?->name,
        ], $isDeposit ? 'Setoran berhasil diposting.' : 'Penarikan berhasil diposting.');

        return $transaction;
    }
}