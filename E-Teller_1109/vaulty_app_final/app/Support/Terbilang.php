<?php

namespace App\Support;

/**
 * Pengubah angka menjadi kata (terbilang) dalam Bahasa Indonesia.
 *
 * Dipakai pada cetakan laporan kas harian, mis.
 * 245000 => "dua ratus empat puluh lima ribu rupiah".
 */
class Terbilang
{
    /** @var array<int, string> */
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    /**
     * Terbilang tanpa satuan mata uang.
     */
    public static function angka(int|float $nilai): string
    {
        $nilai = (int) round(abs($nilai));

        if ($nilai < 12) {
            return self::SATUAN[$nilai] === '' ? 'nol' : self::SATUAN[$nilai];
        }

        if ($nilai < 20) {
            return self::angka($nilai - 10) . ' belas';
        }

        if ($nilai < 100) {
            return self::puluhan($nilai, 10, 'puluh');
        }

        if ($nilai < 200) {
            return 'seratus' . self::sambung($nilai - 100);
        }

        if ($nilai < 1000) {
            return self::ratusan($nilai);
        }

        if ($nilai < 2000) {
            return 'seribu' . self::sambung($nilai - 1000);
        }

        if ($nilai < 1000000) {
            return self::ribuan($nilai, 1000, 'ribu');
        }

        if ($nilai < 1000000000) {
            return self::ribuan($nilai, 1000000, 'juta');
        }

        if ($nilai < 1000000000000) {
            return self::ribuan($nilai, 1000000000, 'miliar');
        }

        return self::ribuan($nilai, 1000000000000, 'triliun');
    }

    /**
     * Terbilang lengkap dengan kata "rupiah".
     */
    public static function rupiah(int|float $nilai): string
    {
        return trim(preg_replace('/\s+/', ' ', self::angka($nilai) . ' rupiah'));
    }

    private static function puluhan(int $nilai, int $pembagi, string $satuan): string
    {
        $depan = intdiv($nilai, $pembagi);
        $sisa = $nilai % $pembagi;

        return self::angka($depan) . ' ' . $satuan . self::sambung($sisa);
    }

    private static function ratusan(int $nilai): string
    {
        $depan = intdiv($nilai, 100);
        $sisa = $nilai % 100;

        return self::angka($depan) . ' ratus' . self::sambung($sisa);
    }

    private static function ribuan(int $nilai, int $pembagi, string $satuan): string
    {
        $depan = intdiv($nilai, $pembagi);
        $sisa = $nilai % $pembagi;

        return self::angka($depan) . ' ' . $satuan . self::sambung($sisa);
    }

    /**
     * Menambahkan sisa nilai di belakang satuan, atau kosong bila habis.
     */
    private static function sambung(int $sisa): string
    {
        return $sisa > 0 ? ' ' . self::angka($sisa) : '';
    }
}
