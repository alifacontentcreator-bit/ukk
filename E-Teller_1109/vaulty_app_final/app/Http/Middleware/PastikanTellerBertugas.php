<?php
/* Tujuan file: Middleware ini memeriksa aturan akses atau kondisi permintaan sebelum fitur pastikan teller bertugas diteruskan ke controller. */


namespace App\Http\Middleware;

use App\Services\ShiftSwapService;
use Closure;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * FILE INI UNTUK: "Pintu Terkunci" — Teller hanya bisa bertransaksi pada hari
 * dia benar-benar bertugas (sesuai jadwal jaga / hasil tukar shift).
 * =============================================================================
 *
 * Contoh: Teller B tidak bisa membuat transaksi hari ini karena jadwal hari ini
 * milik Teller A. Bisa dibuka hanya bila Supervisor menyetujui tukar shift.
 */
class PastikanTellerBertugas
{
    public function __construct(private ShiftSwapService $swapService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $pengguna = $request->user();

        if (!$pengguna || $pengguna->role !== 'teller') {
            return $next($request);
        }

        // Bila Supervisor belum mengatur jadwal hari ini, jangan memblokir
        // (supaya sistem tidak terkunci saat data jadwal belum disiapkan).
        if (!$this->swapService->jadwalHariIniSudahDiatur()) {
            return $next($request);
        }

        if ($this->swapService->bertugasHariIni($pengguna)) {
            return $next($request);
        }

        return back()->withErrors([
            'jadwal' => 'Anda tidak memiliki shift jaga hari ini, sehingga transaksi/laporan belum bisa dilakukan. '
                . 'Silakan ajukan Tukar Shift di halaman Absensi Jaga bila berhalangan atau ingin digantikan.',
        ]);
    }
}
