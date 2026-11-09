<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\DailyReport;
use App\Models\TransactionCorrection;
use Illuminate\Http\Request;

class TransactionCorrectionController extends Controller
{
    private const KODE_KOREKSI = ['K01', 'K02', 'K03', 'K99'];

    public function index(Request $request)
    {
        $tellerId = auth()->id();

        $f = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'type' => ['nullable', 'in:deposit,withdrawal'],
            'q' => ['nullable', 'string', 'max:80'],
            'kstatus' => ['nullable', 'in:pending,approved,rejected'],
            'tx' => ['nullable', 'integer'],
        ]);

        $tanggal = $f['tanggal'] ?? today()->toDateString();
        $laporanTerkirim = DailyReport::where('teller_id', $tellerId)
            ->whereDate('report_date', $tanggal)
            ->where(function ($query) {
                $query->whereNotNull('submitted_at')
                    ->orWhereIn('status', ['submitted', 'approved']);
            })
            ->exists();

        // Riwayat transaksi milik teller ini (bisa dicari per tanggal, jenis, nama/rekening/kode)
        $query = Transaction::with(['account.user'])->where('user_id', $tellerId)->whereDate('created_at', $tanggal);
        if (!empty($f['type'])) {
            $query->where('type', $f['type']);
        }
        if (!empty($f['q'])) {
            $q = trim($f['q']);
            $query->where(function ($w) use ($q) {
                $w->where('transaction_code', 'like', "%{$q}%")
                    ->orWhere('reference_number', 'like', "%{$q}%")
                    ->orWhereHas('account', function ($a) use ($q) {
                        $a->where('account_number', 'like', "%{$q}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"));
                    });
            });
        }
        $riwayat = $query->latest()->paginate(10, ['*'], 'page')->withQueryString();

        // Pilihan transaksi mengikuti tanggal riwayat yang sedang dibuka.
        $pilihan = $laporanTerkirim ? collect() : Transaction::with(['account.user'])
            ->where('user_id', $tellerId)
            ->whereDate('created_at', $tanggal)
            ->whereIn('status', ['approved', 'success'])
            ->latest()
            ->get();

        $corrQuery = TransactionCorrection::with(['transaction.account.user', 'supervisor'])->where('teller_id', $tellerId);
        if (!empty($f['kstatus'])) {
            $corrQuery->where('status', $f['kstatus']);
        }
        $corrections = $corrQuery->latest()->paginate(6, ['*'], 'kpage')->withQueryString();

        $selectedTx = $f['tx'] ?? null;

        return view('teller.koreksi', compact('riwayat', 'pilihan', 'corrections', 'tanggal', 'selectedTx', 'laporanTerkirim'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'jenis_koreksi' => 'required|in:kurang,tambah',
            'amount' => ['required', 'numeric', 'gt:0'],
            'sandi_koreksi' => ['required', 'string', 'in:' . implode(',', self::KODE_KOREKSI)],
            'reason' => 'required|string|max:255',
        ]);

        $wrongTx = Transaction::where('user_id', auth()->id())
            ->whereIn('status', ['approved', 'success'])
            ->findOrFail($validated['transaction_id']);

        $report = DailyReport::where('teller_id', auth()->id())
            ->whereDate('report_date', $wrongTx->created_at->toDateString())
            ->first();

        if ($report && ($report->submitted_at || in_array($report->status, ['submitted', 'approved'], true))) {
            return back()->withErrors([
                'transaction_id' => 'Koreksi tidak dapat diajukan karena laporan harian untuk tanggal transaksi ini sudah pernah dikirim.',
            ]);
        }

        if (($validated['sandi_koreksi'] === 'K01' && $validated['jenis_koreksi'] !== 'kurang')
            || ($validated['sandi_koreksi'] === 'K02' && $validated['jenis_koreksi'] !== 'tambah')) {
            return back()->withErrors([
                'jenis_koreksi' => 'K01 (kelebihan input) harus mengurangi saldo dan K02 (kekurangan input) harus menambah saldo.',
            ]);
        }

        $account = Account::findOrFail($wrongTx->account_id);

        // Cek indikatif SOP Rp 10.000 saat pengajuan.
        // Tetap dicek ULANG oleh Supervisor saat approve, karena saldo bisa
        // saja berubah selagi pengajuan ini masih menunggu.
        if ($validated['jenis_koreksi'] === 'kurang' && ($account->balance - $validated['amount']) < 10000) {
            return back()->withErrors(['amount' => 'Koreksi ditolak: Pengurangan ini menyebabkan saldo di bawah batas minimal Rp 10.000.']);
        }

        // Teller HANYA mengajukan. Saldo & jurnal belum berubah di sini —
        // baru diproses saat Supervisor menekan "Approve" (lihat SupervisorController).
        $correction = TransactionCorrection::create([
            'transaction_id' => $wrongTx->id,
            'teller_id' => auth()->id(),
            'type' => $validated['jenis_koreksi'] === 'kurang' ? 'withdrawal_correction' : 'deposit_correction',
            'corrected_amount' => $validated['amount'],
            'correction_code' => $validated['sandi_koreksi'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        // Jurnal Audit: pengajuan koreksi transaksi tercatat (menunggu validasi).
        \App\Services\TransactionActivityLogger::diajukan(auth()->id(), [
            'type' => $wrongTx->type,
            'amount' => $validated['amount'],
            'account_number' => $account->account_number,
            'reference_number' => $wrongTx->reference_number ?: $wrongTx->transaction_code,
            'nasabah' => $account->user?->name,
        ], 'Pengajuan koreksi ' . $correction->correction_code . ' menunggu validasi Supervisor.');

        return back()->with('success', 'Pengajuan koreksi berhasil dikirim. Menunggu persetujuan Supervisor sebelum saldo nasabah berubah.');
    }

    public function show($id)
    {
        return redirect()->route('teller.koreksi');
    }
}