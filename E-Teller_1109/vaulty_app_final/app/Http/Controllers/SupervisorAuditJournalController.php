<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * FILE INI UNTUK: Halaman "Jurnal Audit" milik SUPERVISOR.
 * =============================================================================
 *
 * Halaman ini menampilkan tiga hal:
 * 1. Kartu ringkasan jumlah transaksi: total, berhasil, gagal, menunggu.
 * 2. Tabel "Jurnal Transaksi" → seluruh transaksi (berhasil & gagal) + alasannya.
 * 3. Tabel "Aktivitas Transaksi" → jejak audit setiap percobaan transaksi
 *    (diajukan / berhasil / gagal) yang ditulis TransactionActivityLogger.
 *
 * Halaman ini HANYA UNTUK DIBACA (tidak ada ubah/hapus data).
 */
class SupervisorAuditJournalController extends Controller
{
    /** Jenis aktivitas transaksi yang dicatat di tabel audit_logs. */
    private const AKTIVITAS_TRANSAKSI = [
        TransactionActivityLogger::ACTION_DIAJUKAN, // menunggu konfirmasi PIN
        TransactionActivityLogger::ACTION_BERHASIL, // transaksi sukses
        TransactionActivityLogger::ACTION_GAGAL,    // transaksi gagal/ditolak
    ];

    /**
     * Menampilkan halaman Jurnal Audit Supervisor (beserta filter).
     */
    public function index(Request $request)
    {
        // 1. Ambil filter dari URL (tanggal, teller, jenis, keterangan, kata kunci).
        $filter = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'in:deposit,withdrawal'],
            'status' => ['nullable', 'in:berhasil,gagal,menunggu,pending,approved,rejected,success,corrected,void'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $keteranganFilter = $filter['status'] ?? null;
        $kataKunci = trim((string) ($filter['q'] ?? ''));

        // 2. Query tabel transaksi + terapkan filter.
        $queryTransaksi = Transaction::with(['account.user', 'teller']);

        $this->terapkanFilterTanggalTeller($queryTransaksi, $filter, 'created_at');
        $this->terapkanFilterJenisKeterangan($queryTransaksi, $filter, $keteranganFilter);
        $this->terapkanFilterKataKunci($queryTransaksi, $kataKunci);

        // 3. Ringkasan angka untuk kartu di atas halaman.
        $statistik = [
            'total' => (clone $queryTransaksi)->count(),
            'berhasil' => (clone $queryTransaksi)->whereIn('status', ['approved', 'success'])->count(),
            'menunggu' => (clone $queryTransaksi)->where('status', 'pending')->count(),
            'gagal' => (clone $queryTransaksi)->whereIn('status', ['rejected', 'void'])->count(),
        ];

        // Tanggal transaksi paling awal → penanda riwayat tersimpan dari dulu.
        $arsipTransaksiPertama = Transaction::min('created_at');

        $transaksi = $queryTransaksi->latest()->paginate(20)->withQueryString();

        // 4. Jejak audit aktivitas transaksi (berhasil maupun gagal).
        $queryAktivitas = AuditLog::with('user')->whereIn('action', self::AKTIVITAS_TRANSAKSI);

        $this->terapkanFilterTanggalTeller($queryAktivitas, $filter, 'created_at');
        $this->terapkanFilterAktivitas($queryAktivitas, $keteranganFilter);

        if ($kataKunci !== '') {
            $queryAktivitas->where('description', 'like', "%{$kataKunci}%");
        }

        $ringkasanAktivitas = [
            'total' => (clone $queryAktivitas)->count(),
            'berhasil' => (clone $queryAktivitas)->where('action', TransactionActivityLogger::ACTION_BERHASIL)->count(),
            'gagal' => (clone $queryAktivitas)->where('action', TransactionActivityLogger::ACTION_GAGAL)->count(),
            'menunggu' => (clone $queryAktivitas)->where('action', TransactionActivityLogger::ACTION_DIAJUKAN)->count(),
        ];

        $aktivitas = $queryAktivitas->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'page_aktivitas')
            ->withQueryString();

        $daftarTeller = User::where('role', 'teller')->orderBy('name')->get();

        return view('supervisor.jurnal', [
            'stats' => $statistik,
            'tellers' => $daftarTeller,
            'transaksi' => $transaksi,
            'awalTransaksi' => $arsipTransaksiPertama,
            'aktivitas' => $aktivitas,
            'ringkasAktivitas' => $ringkasanAktivitas,
        ]);
    }

    /**
     * Filter tanggal awal/akhir + petugas (teller) tertentu.
     */
    private function terapkanFilterTanggalTeller(Builder $query, array $filter, string $kolomTanggal): void
    {
        if (!empty($filter['from'])) {
            $query->whereDate($kolomTanggal, '>=', $filter['from']);
        }

        if (!empty($filter['to'])) {
            $query->whereDate($kolomTanggal, '<=', $filter['to']);
        }

        if (!empty($filter['teller_id'])) {
            $query->where('user_id', $filter['teller_id']);
        }
    }

    /**
     * Filter jenis transaksi (setoran/penarikan) dan keterangan
     * berhasil/gagal/menunggu pada tabel transaksi.
     */
    private function terapkanFilterJenisKeterangan(Builder $query, array $filter, ?string $keterangan): void
    {
        if (!empty($filter['type'])) {
            $query->where('type', $filter['type']);
        }

        // Keterangan ringkas: Berhasil (approved/success), Gagal (rejected/void),
        // Menunggu (pending). Nilai status lama tetap diterima.
        if ($keterangan === 'berhasil') {
            $query->whereIn('status', ['approved', 'success']);
        } elseif ($keterangan === 'gagal') {
            $query->whereIn('status', ['rejected', 'void']);
        } elseif ($keterangan === 'menunggu') {
            $query->where('status', 'pending');
        } elseif (!empty($keterangan)) {
            $query->where('status', $keterangan);
        }
    }

    /**
     * Pencarian pada jurnal transaksi:
     * nomor referensi, kode transaksi, nomor rekening, atau nama nasabah.
     */
    private function terapkanFilterKataKunci(Builder $query, string $kataKunci): void
    {
        if ($kataKunci === '') {
            return;
        }

        $query->where(function (Builder $subQuery) use ($kataKunci) {
            $subQuery->where('reference_number', 'like', "%{$kataKunci}%")
                ->orWhere('transaction_code', 'like', "%{$kataKunci}%")
                ->orWhereHas('account', function (Builder $akun) use ($kataKunci) {
                    $akun->where('account_number', 'like', "%{$kataKunci}%")
                        ->orWhereHas('user', fn (Builder $nasabah) => $nasabah->where('name', 'like', "%{$kataKunci}%"));
                });
        });
    }

    /**
     * Filter tabel aktivitas berdasarkan keterangan yang dipilih,
     * supaya isinya konsisten dengan tabel transaksi.
     */
    private function terapkanFilterAktivitas(Builder $query, ?string $keterangan): void
    {
        if ($keterangan === 'berhasil') {
            $query->where('action', TransactionActivityLogger::ACTION_BERHASIL);
        } elseif ($keterangan === 'gagal') {
            $query->where('action', TransactionActivityLogger::ACTION_GAGAL);
        } elseif ($keterangan === 'menunggu') {
            $query->where('action', TransactionActivityLogger::ACTION_DIAJUKAN);
        }
    }
}
