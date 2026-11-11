<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur petugas notifikasi controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\ReportNotification;

/**
 * =============================================================================
 * FILE INI UNTUK: Kotak masuk notifikasi Supervisor & Admin (ikon lonceng).
 * =============================================================================
 *
 * Sumber data: tabel `report_notifications` (dipakai juga oleh Teller).
 * Dipakai saat ada informasi/pengajuan baru, mis. "Pengajuan tukar shift".
 */
class PetugasNotifikasiController extends Controller
{
    /** Daftar notifikasi milik Supervisor / Admin yang sedang login. */
    public function index()
    {
        $pengguna = auth()->user();

        $notifikasi = ReportNotification::where('user_id', $pengguna->id)
            ->latest()
            ->paginate(20);

        $tampilan = $pengguna->role === 'admin' ? 'admin.notifikasi' : 'supervisor.notifikasi';

        return view($tampilan, [
            'notifikasi' => $notifikasi,
            'jumlahBelumDibaca' => ReportNotification::unreadCountFor((int) $pengguna->id),
        ]);
    }

    /** Tandai semua notifikasi sudah dibaca. */
    public function bacaSemua()
    {
        ReportNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
