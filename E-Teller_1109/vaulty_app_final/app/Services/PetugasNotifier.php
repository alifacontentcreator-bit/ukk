<?php
/* Tujuan file: Service ini menampung proses bisnis bersama untuk fitur petugas notifier agar dapat dipakai konsisten oleh beberapa bagian aplikasi. */


namespace App\Services;

use App\Models\ReportNotification;
use App\Models\User;

/**
 * =============================================================================
 * FILE INI UNTUK: Mengirim notifikasi ke petugas (Teller / Supervisor / Admin).
 * =============================================================================
 *
 * Memakai tabel `report_notifications` yang sudah ada (punya kolom
 * user_id, title, message, type, read_at), sehingga ikon notifikasi
 * (bell) di semua role menampilkan pesan yang sama.
 */
class PetugasNotifier
{
    /** Kirim notifikasi ke satu pengguna. */
    public function kirimKe(int $idPengguna, string $judul, string $pesan, string $tipe = 'info'): void
    {
        ReportNotification::create([
            'user_id' => $idPengguna,
            'daily_report_id' => null,
            'title' => $judul,
            'message' => $pesan,
            'type' => $tipe,
        ]);
    }

    /** Kirim notifikasi ke semua pengguna dengan role tertentu. */
    public function kirimKeRole(string $role, string $judul, string $pesan, string $tipe = 'info'): void
    {
        $penerima = User::where('role', $role)->where('status', 'active')->get();

        foreach ($penerima as $user) {
            $this->kirimKe((int) $user->id, $judul, $pesan, $tipe);
        }
    }

    /** Notifikasi darurat: ke seluruh Supervisor DAN Admin. */
    public function kirimKeSupervisorDanAdmin(string $judul, string $pesan, string $tipe = 'info'): void
    {
        $this->kirimKeRole('supervisor', $judul, $pesan, $tipe);
        $this->kirimKeRole('admin', $judul, $pesan, $tipe);
    }
}
