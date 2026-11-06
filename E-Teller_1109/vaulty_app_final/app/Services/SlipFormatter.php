<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\Terbilang;

/**
 * Bentuk data slip transaksi yang sama untuk API mobile, PDF, dan (acuan)
 * halaman cetak teller.
 */
class SlipFormatter
{
    public static function summary(Transaction $transaction): array
    {
        $issuedAt = $transaction->approved_at ?? $transaction->created_at;
        $amount = (float) $transaction->amount;
        $isDeposit = $transaction->type === 'deposit';

        return [
            'uuid' => (string) $transaction->id,
            'id' => $transaction->id,
            'slip_number' => $transaction->reference_number ?: $transaction->transaction_code,
            'reference_number' => $transaction->reference_number ?: $transaction->transaction_code,
            'type' => $transaction->type,
            'type_label' => $isDeposit ? 'Setoran' : 'Penarikan',
            'amount' => $amount,
            'amount_formatted' => 'Rp' . number_format($amount, 0, ',', '.'),
            'issued_at' => $issuedAt?->toIso8601String(),
            'date' => $issuedAt?->format('d/m/Y'),
            'time' => $issuedAt?->format('H:i') . ' WIB',
            'status' => $transaction->status,
            'is_corrected' => (bool) ($transaction->correction_exists ?? false),
        ];
    }

    public static function detail(Transaction $transaction): array
    {
        $summary = self::summary($transaction);
        $account = $transaction->account;
        $user = $account?->user;
        $amount = (float) $transaction->amount;

        return array_merge($summary, [
            'customer_name' => $user?->name ?? '-',
            'customer_address' => $user?->alamat ?? '',
            'account_number' => $account?->account_number ?? '-',
            'account_number_masked' => self::maskAccount($account?->account_number),
            'description' => $transaction->description
                ?: ($transaction->type === 'deposit' ? 'Setoran Tunai' : 'Penarikan Tunai'),
            'amount_in_words' => ucfirst(Terbilang::rupiah((int) round($amount))),
            'balance_before' => self::balance($transaction->data_before),
            'balance_after' => self::balance($transaction->data_after),
            'teller_name' => $transaction->teller?->name ?? '-',
            'verification_code' => self::verificationCode($transaction),
            'purpose' => $account?->product_type === 'tabungan_karyawan'
                ? 'Tabungan Karyawan'
                : 'Tabungan Siswa',
        ]);
    }

    private static function balance(mixed $data): ?float
    {
        $value = is_array($data) ? ($data['balance'] ?? null) : null;

        return $value === null ? null : (float) $value;
    }

    private static function verificationCode(Transaction $transaction): string
    {
        $seed = ($transaction->reference_number ?: $transaction->transaction_code)
            . '|' . $transaction->id;
        $hash = strtoupper(substr(hash('sha256', $seed), 0, 12));

        return implode('-', str_split($hash, 4));
    }

    private static function maskAccount(?string $number): string
    {
        $number = trim((string) $number);
        if ($number === '') {
            return '-';
        }

        if (strlen($number) <= 4) {
            return $number;
        }

        return str_repeat('•', strlen($number) - 4) . substr($number, -4);
    }
}
