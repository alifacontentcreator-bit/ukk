<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\TransactionCorrectionController;
use App\Http\Controllers\AccountingJournalController;
use App\Http\Controllers\AdminPasswordRecoveryController;
use App\Http\Controllers\AdminPinResetController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SupervisorDutyController;
use App\Http\Controllers\SupervisorAuditJournalController;
use App\Http\Controllers\SupervisorShiftSwapController;
use App\Http\Controllers\TellerShiftSwapController;
use App\Http\Controllers\PetugasNotifikasiController;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\DailyReport;
use App\Models\CashTransfer;
use App\Models\CashRequest;

use App\Services\CashDrawerService;


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('auth.login');
})->name('login');

Route::post('/', [AuthController::class, 'authenticate'])
    ->name('login.process');

Route::get('/lupa-sandi', [AuthController::class, 'forgotPassword'])
    ->name('password.forgot');

Route::post('/lupa-sandi/ajukan', [AuthController::class, 'requestPasswordRecovery'])
    ->name('password.recovery.request');

Route::get('/atur-ulang-sandi/{token}', [AuthController::class, 'showPasswordResetForm'])
    ->name('password.reset.form');

Route::post('/atur-ulang-sandi/{token}', [AuthController::class, 'resetPassword'])
    ->name('password.reset');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

// QR identitas nasabah
Route::get('/scan-rekening/{token}', [CustomerPortalController::class, 'scanAccount'])
    ->name('customer.qr-account');


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 0. PROFIL SENDIRI (semua peran)
    |--------------------------------------------------------------------------
    | Tidak ada parameter id: pengguna hanya bisa membuka profilnya sendiri.
    | throttle:5,1 dipasang pada aksi sensitif (ubah email & ubah sandi).
    */

    Route::get('/profil', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::put('/profil', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profil/email', [ProfileController::class, 'updateEmail'])
        ->name('profile.email')
        ->middleware('throttle:5,1');

    Route::put('/profil/sandi', [ProfileController::class, 'updatePassword'])
        ->name('profile.password')
        ->middleware('throttle:5,1');

    /*
    |--------------------------------------------------------------------------
    | 1. NASABAH
    |--------------------------------------------------------------------------
    */

    Route::prefix('nasabah')
        ->name('customer.')
        ->middleware('role:customer')
        ->group(function () {

            Route::get('/dashboard', [CustomerPortalController::class, 'dashboard'])
                ->name('dashboard');

            Route::get('/saldo', [CustomerPortalController::class, 'saldo'])
                ->name('saldo');

            Route::get('/riwayat', [CustomerPortalController::class, 'history'])
                ->name('history');

            Route::get('/receipt/{transaction}', [CustomerPortalController::class, 'receipt'])
                ->name('receipt');

            Route::get('/notifikasi', [CustomerPortalController::class, 'notifications'])
                ->name('notifications');

            Route::get('/qr-identitas', [CustomerPortalController::class, 'qrIdentity'])
                ->name('qr-data');

            Route::get('/scan-qr', [CustomerPortalController::class, 'qrScanner'])
                ->name('qr-scanner');
        });


    /*
    |--------------------------------------------------------------------------
    | 2. ADMIN
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {

            // Dashboard Admin
            Route::get('/dashboard', function () {

                $totalTeller = User::where('role', 'teller')->count();

                $totalSupervisor = User::where('role', 'supervisor')->count();

                $totalRekening = Account::count();

                $rekeningBlokir = Account::where('status', 'blocked')
                    ->orWhereHas('user', fn ($query) => $query->where('status', 'blocked'))
                    ->count();

                $dbStatus = 'Terhubung';

                try {
                    DB::connection()->getPdo();
                } catch (\Exception $e) {
                    $dbStatus = 'Terputus';
                }

                return view('admin.dashboard', compact(
                    'totalTeller',
                    'totalSupervisor',
                    'totalRekening',
                    'rekeningBlokir',
                    'dbStatus'
                ));

            })->name('dashboard');


            // Manajemen User
            Route::get('/users', [UserController::class, 'index'])
                ->name('users');

            Route::get('/users/create', [UserController::class, 'create'])
                ->name('users.create');

            Route::post('/users', [UserController::class, 'store'])
                ->name('users.store');

            Route::post('/users/import-siswa', [UserController::class, 'importStudents'])
                ->name('users.import-siswa');

            Route::get('/users/template-import-siswa', [UserController::class, 'studentImportTemplate'])
                ->name('users.template-import-siswa');

            Route::get('/users/{user}/edit', [UserController::class, 'edit'])
                ->name('users.edit');

            Route::put('/users/{user}', [UserController::class, 'update'])
                ->name('users.update');

            Route::any('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
                ->name('users.toggle-status');

            Route::delete('/users/{user}', [UserController::class, 'destroy'])
                ->name('users.destroy');


            // Master Rekening
            Route::get('/rekening', [AccountController::class, 'index'])
                ->name('rekening');

            Route::get('/rekening/create', [AccountController::class, 'create'])
                ->name('rekening.create');

            Route::post('/rekening', [AccountController::class, 'store'])
                ->name('rekening.store');

            Route::get('/rekening/{account}/edit', [AccountController::class, 'edit'])
                ->name('rekening.edit');

            Route::put('/rekening/{account}', [AccountController::class, 'update'])
                ->name('rekening.update');

            Route::get('/rekening/{account}/qr', [AccountController::class, 'qr'])
                ->name('rekening.qr');

            Route::post('/rekening/{account}/reset-pin', [AccountController::class, 'resetPin'])
                ->name('rekening.reset-pin');


            // Konfigurasi dan Audit Log
            Route::get('/konfigurasi', [SystemSettingsController::class, 'index'])
                ->name('konfigurasi');

            Route::put('/konfigurasi', [SystemSettingsController::class, 'update'])
                ->name('konfigurasi.update');

            Route::get('/audit-log', [AdminAuditLogController::class, 'index'])
                ->name('audit-log');

            // Notifikasi (ikon lonceng) untuk Admin: informasi & pengajuan dari petugas.
            Route::get('/notifikasi', [PetugasNotifikasiController::class, 'index'])
                ->name('notifikasi');

            Route::post('/notifikasi/baca', [PetugasNotifikasiController::class, 'bacaSemua'])
                ->name('notifikasi.baca');

            Route::get('/pemulihan-sandi', [AdminPasswordRecoveryController::class, 'index'])
                ->name('pemulihan-sandi');

            Route::post('/pemulihan-sandi/{id}/setujui', [AdminPasswordRecoveryController::class, 'approve'])
                ->name('pemulihan-sandi.approve');

            Route::get('/reset-pin', [AdminPinResetController::class, 'index'])
                ->name('reset-pin');

            Route::post('/reset-pin/{id}/approve', [AdminPinResetController::class, 'approve'])
                ->name('reset-pin.approve');

            Route::post('/reset-pin/{id}/reject', [AdminPinResetController::class, 'reject'])
                ->name('reset-pin.reject');


            // Jurnal Akuntansi
            Route::get('/jurnal-akuntansi', [AccountingJournalController::class, 'index'])
                ->name('jurnal');

            Route::get('/jurnal-akuntansi/export-pdf', [AccountingJournalController::class, 'exportPdf'])
                ->name('jurnal.pdf');
        });


    /*
    |--------------------------------------------------------------------------
    | 3. SUPERVISOR
    |--------------------------------------------------------------------------
    */

    Route::prefix('supervisor')
        ->name('supervisor.')
        ->middleware('role:supervisor')
        ->group(function () {

            // Dashboard Supervisor
            Route::get('/dashboard', function () {
                return view('supervisor.dashboard');
            })->name('dashboard');


            // Validasi Transaksi
            Route::get('/validasi', [SupervisorController::class, 'validation'])
                ->name('validasi');

            Route::post('/validasi/{id}/approve', [SupervisorController::class, 'approve'])
                ->name('validasi.approve');

            Route::post('/validasi/{id}/reject', [SupervisorController::class, 'reject'])
                ->name('validasi.reject');


            // ====================================================
            // REKONSILIASI KAS
            // ====================================================

            Route::get('/rekonsiliasi', function (
                Request $request,
                CashDrawerService $cashService
            ) {

                $validated = $request->validate([
                    'tanggal' => ['nullable', 'date'],
                ]);

                $tanggal = $validated['tanggal'] ?? today()->toDateString();

                $totalTeller = User::where('role', 'teller')->count();

                // Data laporan Teller pada tanggal yang dipilih.
                $reports = DailyReport::with('teller')
                    ->whereDate('report_date', $tanggal)
                    ->get()
                    ->map(function ($r) {

                        $r->total_fisik = $r->totalFisik();

                        // Kas akhir sistem = kas awal + setoran - penarikan.
                        $r->total_sistem = $r->saldoAkhirSistem();

                        $r->selisih = $r->selisihKas();

                        return $r;
                    });

                // Ringkasan rekonsiliasi.
                $summary = [
                    'total_teller' => $totalTeller,

                    'sudah_lapor' => $reports->count(),

                    'klop' => $reports->filter(
                        fn ($r) => abs((float) $r->selisih) < 0.01
                    )->count(),

                    'selisih' => $reports->filter(
                        fn ($r) => abs((float) $r->selisih) >= 0.01
                    )->count(),
                ];

                $tellers = User::where('role', 'teller')
                    ->orderBy('name')
                    ->get();

                // Saldo awal brankas: nilai dasar yang berlaku terus-menerus
                // (tidak disimpan per tanggal dan tidak direset setiap hari).
                $vaultInitial = $cashService->vaultInitialBalance();

                // Penyerahan modal yang masih menunggu konfirmasi Teller.
                $openingTransfers = CashTransfer::with('teller')
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $tanggal)
                    ->where('status', 'pending')
                    ->latest()
                    ->get();

                // Total modal yang sudah dikonfirmasi Teller.
                $confirmedOpening = CashTransfer::where('type', 'opening')
                    ->whereDate('transfer_date', $tanggal)
                    ->where('status', 'confirmed')
                    ->sum('amount');

                // Pengembalian kas Teller yang menunggu konfirmasi Supervisor.
                $closingTransfers = CashTransfer::with('teller')
                    ->where('type', 'closing')
                    ->whereDate('transfer_date', $tanggal)
                    ->where('status', 'pending')
                    ->latest()
                    ->get();

                // Permintaan tambah modal dari Teller yang belum diproses.
                $cashRequests = CashRequest::with('teller')
                    ->where('status', CashRequest::STATUS_PENDING)
                    ->latest()
                    ->get();

                // Saldo brankas saat ini: saldo awal ditambah kas yang masuk
                // dikurangi modal yang diserahkan. Dihitung kumulatif dari
                // seluruh pergerakan kas (tidak reset harian dan tidak
                // terpengaruh filter tanggal tabel laporan).
                $vaultBalanceHitung = $cashService->supervisorBalance();

                // Uang fisik di brankas tidak mungkin minus. Bila pergerakan modal
                // melebihi saldo awal, tampilkan Rp 0 dan beri peringatan.
                $vaultShortage = $vaultBalanceHitung < 0 ? abs($vaultBalanceHitung) : 0.0;
                $vaultBalance = max(0.0, $vaultBalanceHitung);

                return view('supervisor.rekonsiliasi', compact(
                    'tanggal',
                    'summary',
                    'reports',
                    'tellers',
                    'vaultInitial',
                    'vaultBalance',
                    'vaultShortage',
                    'openingTransfers',
                    'confirmedOpening',
                    'closingTransfers',
                    'cashRequests'
                ));

            })->name('rekonsiliasi');


            // Pengelolaan Saldo Awal Brankas
            Route::post('/kas/saldo-awal', [ReportController::class, 'setVaultInitialBalance'])
                ->name('kas.saldo-awal');

            // Penyerahan Modal dari Brankas ke Teller
            Route::post('/kas/serahkan-modal', [ReportController::class, 'createOpeningTransfer'])
                ->name('kas.serahkan-modal');

            // Memenuhi Permintaan Tambah Modal dari Teller (nominal ditentukan Supervisor)
            Route::post('/kas/permintaan/{cashRequest}/penuhi', [ReportController::class, 'fulfillOpeningRequest'])
                ->name('kas.penuhi-modal');

            // Konfirmasi Kas Pengembalian dari Teller
            Route::post('/kas/konfirmasi-pengembalian/{transfer}', [ReportController::class, 'confirmClosingTransfer'])
                ->name('kas.konfirmasi-pengembalian');


            // Koreksi Transaksi
            Route::get('/koreksi', [SupervisorController::class, 'koreksiIndex'])
                ->name('koreksi');

            Route::post('/koreksi/{id}/approve', [SupervisorController::class, 'koreksiApprove'])
                ->name('koreksi.approve');

            Route::post('/koreksi/{id}/reject', [SupervisorController::class, 'koreksiReject'])
                ->name('koreksi.reject');

            Route::post('/validasi/koreksi/{id}/approve', [SupervisorController::class, 'koreksiApprove'])
                ->name('validasi.koreksi.approve');

            Route::post('/validasi/koreksi/{id}/reject', [SupervisorController::class, 'koreksiReject'])
                ->name('validasi.koreksi.reject');


            // Petugas Jaga
            Route::get('/petugas/absensi', [SupervisorDutyController::class, 'attendanceIndex'])
                ->name('absensi');

            Route::post('/petugas/absensi/tandai', [SupervisorDutyController::class, 'attendanceMark'])
                ->name('absensi.tandai');

            Route::get('/petugas/absensi/export', [SupervisorDutyController::class, 'attendanceExport'])
                ->name('absensi.export');

            Route::get('/petugas/jadwal', [SupervisorDutyController::class, 'scheduleIndex'])
                ->name('jadwal');

            Route::post('/petugas/jadwal', [SupervisorDutyController::class, 'scheduleStore'])
                ->name('jadwal.store');

            // Ubah jadwal (shift / petugas) untuk hari tertentu atau semua hari.
            Route::post('/petugas/jadwal/ubah', [SupervisorDutyController::class, 'scheduleBulkUpdate'])
                ->name('jadwal.bulk-update');

            // Hapus jadwal untuk hari tertentu atau semua hari.
            Route::delete('/petugas/jadwal', [SupervisorDutyController::class, 'scheduleBulkDestroy'])
                ->name('jadwal.bulk-destroy');

            Route::delete('/petugas/jadwal/{id}', [SupervisorDutyController::class, 'scheduleDestroy'])
                ->name('jadwal.destroy');

            Route::post('/petugas/shift', [SupervisorDutyController::class, 'shiftStore'])
                ->name('shift.store');

            Route::post('/petugas/shift/{id}/toggle', [SupervisorDutyController::class, 'shiftToggle'])
                ->name('shift.toggle');

            Route::delete('/petugas/shift/{id}', [SupervisorDutyController::class, 'shiftDestroy'])
                ->name('shift.destroy');

            Route::get('/petugas/data', [SupervisorDutyController::class, 'officerIndex'])
                ->name('petugas');


            // ===================== TUKAR SHIFT (PERSETUJUAN) =====================
            // Daftar pengajuan tukar shift dari Teller.
            Route::get('/petugas/tukar-shift', [SupervisorShiftSwapController::class, 'index'])
                ->name('tukar-shift');

            // Klik "Setujui" → hak jaga pindah dari Teller A ke Teller B.
            Route::post('/petugas/tukar-shift/{swap}/setujui', [SupervisorShiftSwapController::class, 'setujui'])
                ->name('tukar-shift.setujui');

            // Klik "Tolak" → pengajuan ditolak dengan alasan.
            Route::post('/petugas/tukar-shift/{swap}/tolak', [SupervisorShiftSwapController::class, 'tolak'])
                ->name('tukar-shift.tolak');


            // ===================== NOTIFIKASI (IKON LONCENG) =====================
            Route::get('/notifikasi', [PetugasNotifikasiController::class, 'index'])
                ->name('notifikasi');

            Route::post('/notifikasi/baca', [PetugasNotifikasiController::class, 'bacaSemua'])
                ->name('notifikasi.baca');


            // Laporan Harian Teller
            Route::get('/laporan', [ReportController::class, 'index'])
                ->name('laporan');

            Route::get('/laporan/cetak', [ReportController::class, 'print'])
                ->name('laporan.cetak');

            Route::post('/laporan/{report}/approve', [ReportController::class, 'approve'])
                ->name('laporan.approve');

            Route::post('/laporan/{report}/reject', [ReportController::class, 'reject'])
                ->name('laporan.reject');

            Route::get('/laporan/{report}/cetak', [ReportController::class, 'printDailyReport'])
                ->name('laporan.cetak-laporan');

            Route::post('/laporan/{report}/reopen', [ReportController::class, 'reopen'])
                ->name('laporan.reopen');

            Route::post('/laporan/{report}/transfer', [ReportController::class, 'transferToVault'])
                ->name('laporan.transfer');


            // Jurnal Audit Supervisor (halaman hanya-baca: transaksi berhasil & gagal).
            Route::get('/jurnal', [SupervisorAuditJournalController::class, 'index'])
                ->name('jurnal');
        });


    /*
    |--------------------------------------------------------------------------
    | 4. TELLER
    |--------------------------------------------------------------------------
    */

    Route::prefix('teller')
        ->name('teller.')
        ->middleware('role:teller')
        ->group(function () {

            // Dashboard Teller
            Route::get('/dashboard', function (
                CashDrawerService $cashService
            ) {

                $tanggal = today()->toDateString();

                $tx = Transaction::where('user_id', auth()->id())
                    ->whereDate('created_at', $tanggal);

                $setoran = (clone $tx)
                    ->where('type', 'deposit')
                    ->whereIn('status', ['approved', 'success'])
                    ->sum('amount');

                $penarikan = (clone $tx)
                    ->where('type', 'withdrawal')
                    ->whereIn('status', ['approved', 'success'])
                    ->sum('amount');

                // Modal yang masih menunggu konfirmasi Teller.
                $openingTransfer = CashTransfer::where('teller_id', auth()->id())
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $tanggal)
                    ->where('status', 'pending')
                    ->latest()
                    ->first();

                // Modal yang sudah diterima Teller.
                $modalDiterima = CashTransfer::where('teller_id', auth()->id())
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $tanggal)
                    ->where('status', 'confirmed')
                    ->sum('amount');

                // Kas akhir yang tersedia di laci Teller.
                $uangDiLaci = $cashService->tellerBalance(
                    auth()->id(),
                    $tanggal
                );

                // Pengembalian kas hari ini (bila sudah diajukan/dikonfirmasi).
                $closingTransfer = CashTransfer::where('teller_id', auth()->id())
                    ->where('type', 'closing')
                    ->whereDate('transfer_date', $tanggal)
                    ->latest()
                    ->first();

                // Permintaan tambah modal hari ini (untuk status di dashboard).
                $cashRequest = CashRequest::where('teller_id', auth()->id())
                    ->whereDate('request_date', $tanggal)
                    ->latest()
                    ->first();

                return view('teller.dashboard', compact(
                    'setoran',
                    'penarikan',
                    'uangDiLaci',
                    'openingTransfer',
                    'modalDiterima',
                    'closingTransfer',
                    'cashRequest'
                ));

            })->name('dashboard');


            // Transaksi
            Route::get('/transaksi', [TransactionController::class, 'create'])
                ->name('transaksi.create');

            Route::post('/transaksi', [TransactionController::class, 'store'])
                ->name('transaksi.store');

            Route::get('/transaksi/permintaan/{approval}/status', [TransactionController::class, 'approvalStatus'])
                ->name('transaksi.approval-status');

            Route::get('/transaksi/{id}/cetak', [TransactionController::class, 'printSlip'])
                ->name('transaksi.cetak');

            Route::get('/qr-identitas/{token}', [TransactionController::class, 'resolveAccountQr'])
                ->name('qr-identitas.resolve');


            // Koreksi Transaksi
            Route::get('/koreksi', [TransactionCorrectionController::class, 'index'])
                ->name('koreksi');

            Route::post('/koreksi', [TransactionCorrectionController::class, 'store'])
                ->name('koreksi.store')
                // Teller hanya boleh mengajukan koreksi pada hari dia bertugas.
                ->middleware('teller.bertugas');

            Route::get('/koreksi/{id}', [TransactionCorrectionController::class, 'show'])
                ->name('koreksi.show');


            // Absensi Teller
            Route::get('/absensi', [AttendanceController::class, 'index'])
                ->name('absensi');

            Route::post('/absensi/masuk', [AttendanceController::class, 'checkIn'])
                ->name('absensi.masuk');

            Route::post('/absensi/pulang', [AttendanceController::class, 'checkOut'])
                ->name('absensi.pulang');


            // ===================== TUKAR SHIFT =====================
            // Teller mengajukan tukar shift (form ada di halaman Absensi Jaga).
            Route::post('/tukar-shift', [TellerShiftSwapController::class, 'store'])
                ->name('tukar-shift.store');

            // Teller membatalkan pengajuan yang masih menunggu.
            Route::delete('/tukar-shift/{swap}', [TellerShiftSwapController::class, 'batalkan'])
                ->name('tukar-shift.batalkan');


            // Konfirmasi Penerimaan Modal dari Supervisor
            Route::post('/kas/konfirmasi-modal/{transfer}', [ReportController::class, 'confirmOpeningTransfer'])
                ->name('kas.konfirmasi-modal')
                ->middleware('teller.bertugas');

            // Pengajuan Tambah Modal ke Supervisor (nominal ditentukan Supervisor)
            Route::post('/kas/ajukan-modal', [ReportController::class, 'requestOpeningModal'])
                ->name('kas.ajukan-modal')
                ->middleware('teller.bertugas');


            // Pengembalian Kas dari Laci Teller ke Brankas
            Route::post('/kas/serahkan-kembali', [ReportController::class, 'createClosingTransfer'])
                ->name('kas.serahkan-kembali')
                ->middleware('teller.bertugas');


            // Laporan Harian Teller
            Route::get('/laporan', [ReportController::class, 'tellerIndex'])
                ->name('laporan');

            Route::post('/laporan/draft', [ReportController::class, 'createTeller'])
                ->name('laporan-harian.draft')
                ->middleware('teller.bertugas');

            Route::post('/laporan/kirim', [ReportController::class, 'sendTeller'])
                ->name('laporan-harian.kirim')
                ->middleware('teller.bertugas');

            Route::post('/laporan/{report}/revisi', [ReportController::class, 'reviseTeller'])
                ->name('laporan-harian.revisi')
                ->middleware('teller.bertugas');

            Route::post('/laporan', [ReportController::class, 'storeTeller'])
                ->name('laporan-harian.store')
                ->middleware('teller.bertugas');


            // Notifikasi keputusan Supervisor atas laporan harian
            Route::get('/notifikasi', [ReportController::class, 'notifications'])
                ->name('notifikasi');


            // Cetak rekap laporan harian (penutupan kas)
            Route::get('/laporan/{report}/cetak', [ReportController::class, 'printDailyReport'])
                ->name('laporan.cetak');
        });

});
