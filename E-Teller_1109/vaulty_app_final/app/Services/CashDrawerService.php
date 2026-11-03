<?php

namespace App\Services;

use App\Models\CashTransfer;
use App\Models\Transaction;

class CashDrawerService
{
    /**
     * Menghitung saldo brankas Supervisor pada tanggal tertentu.
     *
     * Saldo brankas = saldo awal - seluruh modal yang dialokasikan
     * kepada Teller (pending maupun confirmed) + kas yang sudah
     * dikembalikan Teller ke brankas (confirmed).
     */
    public function supervisorBalance(
        float $initialBalance,
        ?string $date = null
    ): float {
        $date = $date ?? today()->toDateString();

        $modalDiserahkan = CashTransfer::whereDate('transfer_date', $date)
            ->where('type', 'opening')
            ->whereIn('status', ['pending', 'confirmed'])
            ->sum('amount');

        // Kas yang sudah benar-benar diterima kembali di brankas.
        $kasKembali = CashTransfer::whereDate('transfer_date', $date)
            ->where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');

        return round(
            $initialBalance - (float) $modalDiserahkan + (float) $kasKembali,
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