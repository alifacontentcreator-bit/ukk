<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\DailyReportReopenLog;
use App\Models\DailyReport;
use App\Models\User;
use App\Models\Transaction;
use App\Models\CashTransfer;
use App\Models\SystemSetting;

use App\Services\CashDrawerService;

class ReportController extends Controller
{
    // ========================================================
    // 1. BAGIAN SUPERVISOR
    // ========================================================

    public function index(Request $request)
    {
        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:submitted,approved,rejected'],
        ]);

        $query = DailyReport::with([
            'teller',
            'approver',
            'reopener',
            'reopenLogs',
        ])->whereIn('status', [
            'submitted',
            'approved',
            'rejected',
        ]);

        if (!empty($f['from'])) {
            $query->whereDate('report_date', '>=', $f['from']);
        }

        if (!empty($f['to'])) {
            $query->whereDate('report_date', '<=', $f['to']);
        }

        if (!empty($f['teller_id'])) {
            $query->where('teller_id', $f['teller_id']);
        }

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }

        $reports = $query
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
            ->latest('report_date')
            ->paginate(8)
            ->withQueryString();

        $tellers = User::where('role', 'teller')
            ->orderBy('name')
            ->get();

        $counts = [
            'menunggu' => DailyReport::where('status', 'submitted')->count(),
            'disetujui' => DailyReport::where('status', 'approved')->count(),
            'ditolak' => DailyReport::where('status', 'rejected')->count(),
            'siap_pindah' => DailyReport::where('status', 'approved')
                ->where('transfer_status', 'pending')
                ->count(),
        ];

        return view('supervisor.laporan', compact(
            'reports',
            'tellers',
            'counts'
        ));
    }

    /**
     * Supervisor menyetujui laporan Teller setelah kas fisik
     * sesuai dengan kas akhir sistem.
     */
    public function approve(Request $request, DailyReport $report)
    {
        try {
            DB::transaction(function () use ($report) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'submitted') {
                    throw new \DomainException(
                        'Laporan ini bukan laporan yang sedang menunggu persetujuan.'
                    );
                }

                $totalPecahanFisik =
                    ((int) $report->pecahan_100k * 100000) +
                    ((int) $report->pecahan_50k * 50000) +
                    ((int) $report->pecahan_20k * 20000);

                $totalSistem =
                    (float) $report->saldo_awal +
                    (float) $report->expected_cash;

                $selisih = $totalPecahanFisik - $totalSistem;

                if (abs($selisih) >= 0.01) {
                    throw new \DomainException(
                        'Laporan belum dapat disetujui karena kas fisik dan kas sistem masih memiliki selisih sebesar Rp ' .
                        number_format(abs($selisih), 0, ',', '.') . '.'
                    );
                }

                $report->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'rejected_reason' => null,
                ]);
            });

            return back()->with(
                'success',
                'Laporan berhasil disetujui. Kas fisik dan kas sistem sudah sesuai.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Supervisor membuka kembali laporan yang ditolak.
     */
    public function reopen(Request $request, DailyReport $report)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembukaan kembali wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ]);

        try {
            DB::transaction(function () use ($report, $validated) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'rejected') {
                    throw new \DomainException(
                        'Hanya laporan yang ditolak yang dapat dibuka kembali.'
                    );
                }

                DailyReportReopenLog::create([
                    'daily_report_id' => $report->id,
                    'supervisor_id' => auth()->id(),
                    'reason' => $validated['reason'],
                    'reopened_at' => now(),
                ]);

                $report->update([
                    'status' => 'draft',
                    'approved_by' => null,
                    'approved_at' => null,
                    'reopened_at' => now(),
                    'reopened_by' => auth()->id(),
                    'reopen_reason' => $validated['reason'],
                ]);
            });

            return back()->with(
                'success',
                'Laporan berhasil dibuka kembali. Teller dapat memperbaiki laporan.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cetak laporan kas harian Supervisor.
     */
    public function print(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = $validated['tanggal'] ?? today()->toDateString();

        $tx = Transaction::whereDate('created_at', $tanggal);

        $setoran = (clone $tx)
            ->where('type', 'deposit')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $penarikan = (clone $tx)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $administrasi = 0;

        $key = 'vault_initial_balance_' . $tanggal;

        $saldoAwalSistem = (float) (
            SystemSetting::where('key', $key)->value('value') ?? 0
        );

        $saldoAkhirSistem = app(CashDrawerService::class)
            ->supervisorBalance($saldoAwalSistem, $tanggal);

        return view('supervisor.laporan-harian-cetak', compact(
            'tanggal',
            'saldoAwalSistem',
            'setoran',
            'penarikan',
            'administrasi',
            'saldoAkhirSistem'
        ));
    }

    // ========================================================
    // 2. PENGELOLAAN KAS SUPERVISOR
    // ========================================================

    /**
     * Menetapkan saldo awal brankas untuk hari ini.
     */
    public function setVaultInitialBalance(Request $request)
    {
        $validated = $request->validate([
            'initial_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $date = today()->toDateString();
        $key = 'vault_initial_balance_' . $date;

        try {
            DB::transaction(function () use ($validated, $date, $key) {
                $existingTransfer = CashTransfer::whereDate(
                    'transfer_date',
                    $date
                )->lockForUpdate()->exists();

                if ($existingTransfer) {
                    throw new \DomainException(
                        'Saldo awal tidak dapat diubah karena proses kas hari ini sudah dimulai.'
                    );
                }

                $setting = SystemSetting::where('key', $key)
                    ->lockForUpdate()
                    ->first();

                if (!$setting) {
                    $setting = new SystemSetting();
                    $setting->key = $key;
                }

                $setting->value = $validated['initial_balance'];
                $setting->save();
            });

            return back()->with(
                'success',
                'Saldo awal brankas hari ini berhasil disimpan.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Saldo awal brankas gagal disimpan.',
            ]);
        }
    }

    /**
     * Supervisor menyerahkan modal kepada Teller.
     */
    public function createOpeningTransfer(Request $request)
    {
        $validated = $request->validate([
            'teller_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $date = today()->toDateString();
                $key = 'vault_initial_balance_' . $date;

                $teller = User::whereKey($validated['teller_id'])
                    ->where('role', 'teller')
                    ->lockForUpdate()
                    ->first();

                if (!$teller) {
                    throw new \DomainException(
                        'Pengguna yang dipilih bukan Teller.'
                    );
                }

                $setting = SystemSetting::where('key', $key)
                    ->lockForUpdate()
                    ->first();

                if (!$setting) {
                    throw new \DomainException(
                        'Supervisor harus menetapkan saldo awal brankas hari ini terlebih dahulu.'
                    );
                }

                $existing = CashTransfer::where('teller_id', $teller->id)
                    ->where('type', 'opening')
                    ->whereDate('transfer_date', $date)
                    ->first();

                if ($existing) {
                    throw new \DomainException(
                        'Penyerahan modal untuk Teller ini sudah tercatat hari ini.'
                    );
                }

                $cashService = app(CashDrawerService::class);

                $vaultBalance = $cashService->supervisorBalance(
                    (float) $setting->value,
                    $date
                );

                if ((float) $validated['amount'] > $vaultBalance) {
                    throw new \DomainException(
                        'Saldo brankas tidak mencukupi untuk penyerahan modal.'
                    );
                }

                CashTransfer::create([
                    'teller_id' => $teller->id,
                    'type' => 'opening',
                    'transfer_date' => $date,
                    'amount' => $validated['amount'],
                    'status' => 'pending',
                    'created_by' => auth()->id(),
                    'description' => $validated['description'] ?? null,
                ]);
            });

            return back()->with(
                'success',
                'Penyerahan modal tercatat. Teller harus mengonfirmasi penerimaan.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Penyerahan modal gagal dibuat. Periksa data dan struktur tabel.',
            ]);
        }
    }

    // ========================================================
    // 3. KONFIRMASI MODAL OLEH TELLER
    // ========================================================

    /**
     * Teller mengonfirmasi penerimaan modal dari Supervisor.
     */
    public function confirmOpeningTransfer(CashTransfer $transfer)
    {
        try {
            DB::transaction(function () use ($transfer) {
                $transfer = CashTransfer::whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $transfer->teller_id !== (int) auth()->id()) {
                    throw new \DomainException(
                        'Kamu tidak memiliki akses untuk mengonfirmasi modal Teller lain.'
                    );
                }

                if ($transfer->type !== 'opening') {
                    throw new \DomainException(
                        'Transfer ini bukan penyerahan modal.'
                    );
                }

                if ($transfer->status !== 'pending') {
                    throw new \DomainException(
                        'Penyerahan modal ini sudah diproses.'
                    );
                }

                $transfer->update([
                    'status' => 'confirmed',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Modal berhasil dikonfirmasi dan masuk ke kas Teller.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);
        }
    }

    // ========================================================
    // 4. PENGEMBALIAN KAS TELLER KE BRANKAS
    // ========================================================

    /**
     * Teller mengajukan pengembalian kas dari laci ke brankas.
     *
     * Uang baru dianggap keluar dari laci setelah Supervisor
     * mengonfirmasi penerimaan kas.
     */
    public function createClosingTransfer(
        Request $request,
        CashDrawerService $cashService
    ) {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Jumlah kas yang dikembalikan wajib diisi.',
            'amount.numeric' => 'Jumlah kas harus berupa angka.',
            'amount.min' => 'Jumlah kas yang dikembalikan minimal Rp 1.',
            'description.max' => 'Keterangan maksimal 500 karakter.',
        ]);

        try {
            DB::transaction(function () use ($validated, $cashService) {
                $tellerId = (int) auth()->id();
                $date = today()->toDateString();
                $amount = round((float) $validated['amount'], 2);

                $teller = User::whereKey($tellerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($teller->role !== 'teller') {
                    throw new \DomainException(
                        'Hanya Teller yang dapat mengajukan pengembalian kas.'
                    );
                }

                $existing = CashTransfer::where('teller_id', $tellerId)
                    ->where('type', 'closing')
                    ->whereDate('transfer_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === 'pending') {
                    throw new \DomainException(
                        'Pengembalian kas hari ini sudah diajukan dan masih menunggu konfirmasi Supervisor.'
                    );
                }

                if ($existing && $existing->status === 'confirmed') {
                    throw new \DomainException(
                        'Pengembalian kas hari ini sudah dikonfirmasi Supervisor dan tidak dapat diajukan lagi.'
                    );
                }

                $uangDiLaci = $cashService->tellerBalance($tellerId, $date);

                if ($uangDiLaci <= 0) {
                    throw new \DomainException(
                        'Tidak ada kas di laci yang dapat dikembalikan.'
                    );
                }

                if ($amount > $uangDiLaci + 0.01) {
                    throw new \DomainException(
                        'Jumlah pengembalian melebihi kas yang tersedia di laci sebesar Rp ' .
                        number_format($uangDiLaci, 0, ',', '.') . '.'
                    );
                }

                $data = [
                    'amount' => $amount,
                    'status' => 'pending',
                    'description' => $validated['description'] ?? null,
                    'created_by' => $tellerId,
                    'confirmed_by' => null,
                    'confirmed_at' => null,
                ];

                if ($existing) {
                    // Baris berstatus "cancelled" dipakai ulang agar tidak
                    // melanggar unique (teller_id, type, transfer_date).
                    $existing->update($data);
                } else {
                    CashTransfer::create(array_merge($data, [
                        'teller_id' => $tellerId,
                        'type' => 'closing',
                        'transfer_date' => $date,
                    ]));
                }
            });

            return back()->with(
                'success',
                'Pengembalian kas diajukan. Serahkan uang kepada Supervisor untuk dikonfirmasi.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Pengembalian kas gagal diajukan. Periksa data dan struktur tabel.',
            ]);
        }
    }

    /**
     * Supervisor mengonfirmasi kas pengembalian dari Teller
     * benar-benar sudah diterima di brankas.
     */
    public function confirmClosingTransfer(CashTransfer $transfer)
    {
        try {
            DB::transaction(function () use ($transfer) {
                $transfer = CashTransfer::whereKey($transfer->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transfer->type !== 'closing') {
                    throw new \DomainException(
                        'Transfer ini bukan pengembalian kas dari Teller.'
                    );
                }

                if ($transfer->status === 'confirmed') {
                    throw new \DomainException(
                        'Pengembalian kas ini sudah dikonfirmasi.'
                    );
                }

                if ($transfer->status !== 'pending') {
                    throw new \DomainException(
                        'Pengembalian kas ini sudah dibatalkan dan tidak dapat dikonfirmasi.'
                    );
                }

                $transfer->update([
                    'status' => 'confirmed',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Kas pengembalian Teller sudah dikonfirmasi masuk ke brankas.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'kas' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'kas' => 'Konfirmasi pengembalian kas gagal disimpan.',
            ]);
        }
    }

    /**
     * Supervisor mencatat kas laporan harian yang sudah disetujui
     * benar-benar dipindahkan ke brankas.
     */
    public function transferToVault(DailyReport $report)
    {
        try {
            DB::transaction(function () use ($report) {
                $report = DailyReport::whereKey($report->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($report->status !== 'approved') {
                    throw new \DomainException(
                        'Hanya laporan yang sudah disetujui yang dapat dipindahkan kasnya ke brankas.'
                    );
                }

                if ($report->transfer_status === 'approved') {
                    throw new \DomainException(
                        'Kas laporan ini sudah tercatat di brankas.'
                    );
                }

                $report->update([
                    'transfer_status' => 'approved',
                    'transferred_by' => auth()->id(),
                    'transferred_at' => now(),
                ]);
            });

            return back()->with(
                'success',
                'Kas laporan sudah tercatat masuk ke brankas.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'laporan' => $e->getMessage(),
            ]);

        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'laporan' => 'Kas laporan gagal dicatat ke brankas.',
            ]);
        }
    }

    // ========================================================
    // 5. LAPORAN HARIAN TELLER
    // ========================================================

    /**
     * Halaman laporan harian Teller.
     */
    public function tellerIndex(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['nullable', 'date'],
        ]);

        $tanggal = $validated['tanggal'] ?? today()->toDateString();
        $tellerName = auth()->user()->name;

        $tx = Transaction::with('account.user')
            ->where('user_id', auth()->id())
            ->whereDate('created_at', $tanggal);

        $setoran = (clone $tx)
            ->where('type', 'deposit')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $penarikan = (clone $tx)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $transactions = (clone $tx)->latest()->get();

        $administrasi = 0;

        $modalHariIni = CashTransfer::where('teller_id', auth()->id())
            ->whereDate('transfer_date', $tanggal)
            ->where('type', 'opening')
            ->where('status', 'confirmed')
            ->value('amount');

        $saldoAwalSistem = (float) ($modalHariIni ?? 0);

        // Kas yang sudah dikembalikan Teller ke brankas hari ini.
        $pengembalian = (float) CashTransfer::where('teller_id', auth()->id())
            ->whereDate('transfer_date', $tanggal)
            ->where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');

        $saldoAkhirSistem =
            $saldoAwalSistem + $setoran + $administrasi - $penarikan - $pengembalian;

        $reportHariIni = DailyReport::where('teller_id', auth()->id())
            ->whereDate('report_date', $tanggal)
            ->first();

        $reports = DailyReport::where('teller_id', auth()->id())
            ->latest('report_date')
            ->latest()
            ->get();

        $summary = [
            'deposit_total' => $setoran,
            'withdrawal_total' => $penarikan,
            'transaction_count' => (clone $tx)->count(),
        ];

        return view('teller.laporan-harian', compact(
            'reportHariIni',
            'transactions',
            'reports',
            'summary',
            'tanggal',
            'tellerName',
            'saldoAwalSistem',
            'setoran',
            'penarikan',
            'pengembalian',
            'administrasi',
            'saldoAkhirSistem'
        ));
    }

    /**
     * Menghitung mutasi transaksi harian Teller.
     * Nilai expected_cash adalah setoran dikurangi penarikan dan
     * pengembalian kas ke brankas, belum termasuk saldo awal modal Teller.
     */
    private function reportData(): array
    {
        $tx = Transaction::where('user_id', auth()->id())
            ->whereDate('created_at', today());

        $deposit = (clone $tx)
            ->where('type', 'deposit')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        $withdrawal = (clone $tx)
            ->where('type', 'withdrawal')
            ->whereIn('status', ['approved', 'success'])
            ->sum('amount');

        // Kas yang sudah dikembalikan Teller ke brankas pada hari ini.
        $closing = CashTransfer::where('teller_id', auth()->id())
            ->whereDate('transfer_date', today())
            ->where('type', 'closing')
            ->where('status', 'confirmed')
            ->sum('amount');

        return [
            'deposit_total' => $deposit,
            'withdrawal_total' => $withdrawal,
            'transaction_count' => (clone $tx)->count(),
            'expected_cash' => $deposit - $withdrawal - $closing,
        ];
    }

    /**
     * Teller membuat draft laporan harian.
     */
    public function createTeller()
    {
        try {
            DB::transaction(function () {
                $tellerId = auth()->id();
                $date = today()->toDateString();

                User::whereKey($tellerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $report = DailyReport::where('teller_id', $tellerId)
                    ->whereDate('report_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($report && $report->status !== 'draft') {
                    throw new \DomainException(
                        'Laporan sudah dikirim atau diproses. Teller tidak dapat membuat ulang draft.'
                    );
                }

                $modalHariIni = CashTransfer::where('teller_id', $tellerId)
                    ->whereDate('transfer_date', $date)
                    ->where('type', 'opening')
                    ->where('status', 'confirmed')
                    ->value('amount');

                if ($modalHariIni === null) {
                    throw new \DomainException(
                        'Modal hari ini belum dikonfirmasi oleh Teller.'
                    );
                }

                $data = array_merge($this->reportData(), [
                    'saldo_awal' => $modalHariIni,
                ]);

                if ($report) {
                    $report->update($data);
                } else {
                    DailyReport::create(array_merge($data, [
                        'teller_id' => $tellerId,
                        'report_date' => $date,
                        'status' => 'draft',
                        'transfer_status' => 'pending',
                    ]));
                }
            });

            return back()->with(
                'success',
                'Draft laporan hari ini berhasil dibuat atau diperbarui.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Teller mengirim laporan akhir kepada Supervisor.
     */
    public function sendTeller(Request $request)
    {
        $validated = $request->validate([
            'pecahan_100k' => ['required', 'integer', 'min:0'],
            'pecahan_50k' => ['required', 'integer', 'min:0'],
            'pecahan_20k' => ['required', 'integer', 'min:0'],
        ], [
            'pecahan_100k.required' => 'Jumlah uang pecahan Rp100.000 wajib diisi.',
            'pecahan_50k.required' => 'Jumlah uang pecahan Rp50.000 wajib diisi.',
            'pecahan_20k.required' => 'Jumlah uang pecahan Rp20.000 wajib diisi.',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $tellerId = auth()->id();
                $date = today()->toDateString();

                User::whereKey($tellerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $report = DailyReport::where('teller_id', $tellerId)
                    ->whereDate('report_date', $date)
                    ->lockForUpdate()
                    ->first();

                if (!$report) {
                    throw new \DomainException(
                        'Buat draft laporan terlebih dahulu.'
                    );
                }

                if ($report->status !== 'draft') {
                    throw new \DomainException(
                        'Laporan sudah dikirim atau diproses. Laporan tidak dapat dikirim ulang.'
                    );
                }

                $modalHariIni = CashTransfer::where('teller_id', $tellerId)
                    ->whereDate('transfer_date', $date)
                    ->where('type', 'opening')
                    ->where('status', 'confirmed')
                    ->value('amount');

                if ($modalHariIni === null) {
                    throw new \DomainException(
                        'Modal hari ini belum dikonfirmasi.'
                    );
                }

                $legacyPending = Transaction::where('user_id', $tellerId)
                    ->whereDate('created_at', $date)
                    ->where('status', 'pending')
                    ->count();

                if ($legacyPending > 0) {
                    throw new \DomainException(
                        "Masih ada {$legacyPending} transaksi yang belum diproses. Selesaikan terlebih dahulu."
                    );
                }

                $data = $this->reportData();

                $report->update(array_merge($data, [
                    'saldo_awal' => $modalHariIni,
                    'pecahan_100k' => $validated['pecahan_100k'],
                    'pecahan_50k' => $validated['pecahan_50k'],
                    'pecahan_20k' => $validated['pecahan_20k'],
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]));
            });

            return back()->with(
                'success',
                'Laporan kas akhir berhasil dikirim kepada Supervisor.'
            );

        } catch (\DomainException $e) {
            return back()->withErrors([
                'report' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Alias untuk route lama.
     */
    public function storeTeller(Request $request)
    {
        return $this->sendTeller($request);
    }
}
