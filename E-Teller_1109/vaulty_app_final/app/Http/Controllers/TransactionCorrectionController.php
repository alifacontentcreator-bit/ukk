<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
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

        // Pilihan transaksi untuk form koreksi: transaksi hari ini milik teller ini
        $pilihan = Transaction::with(['account.user'])
            ->where('user_id', $tellerId)
            ->whereDate('created_at', today())
            ->latest()
            ->get();

        $corrQuery = TransactionCorrection::with(['transaction.account.user', 'supervisor'])->where('teller_id', $tellerId);
        if (!empty($f['kstatus'])) {
            $corrQuery->where('status', $f['kstatus']);
        }
        $corrections = $corrQuery->latest()->paginate(6, ['*'], 'kpage')->withQueryString();

        $selectedTx = $f['tx'] ?? null;

        return view('teller.koreksi', compact('riwayat', 'pilihan', 'corrections', 'tanggal', 'selectedTx'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'jenis_koreksi' => 'required|in:kurang,tambah',
            'amount' => ['required', 'numeric', $request->input('jenis_koreksi') === 'tambah' ? 'min:5000' : 'gt:0'],
            'sandi_koreksi' => ['required', 'string', 'in:' . implode(',', self::KODE_KOREKSI)],
            'reason' => 'required|string|max:255',
        ]);

        $wrongTx = Transaction::findOrFail($request->transaction_id);
        $account = Account::findOrFail($wrongTx->account_id);

        // Cek indikatif SOP Rp 10.000 saat pengajuan.
        // Tetap dicek ULANG oleh Supervisor saat approve, karena saldo bisa
        // saja berubah selagi pengajuan ini masih menunggu.
        if ($request->jenis_koreksi === 'kurang' && ($account->balance - $request->amount) < 10000) {
            return back()->withErrors(['amount' => 'Koreksi ditolak: Pengurangan ini menyebabkan saldo di bawah batas minimal Rp 10.000.']);
        }

        // Teller HANYA mengajukan. Saldo & jurnal belum berubah di sini —
        // baru diproses saat Supervisor menekan "Approve" (lihat SupervisorController).
        TransactionCorrection::create([
            'transaction_id' => $wrongTx->id,
            'teller_id' => auth()->id(),
            'type' => $request->jenis_koreksi === 'kurang' ? 'withdrawal_correction' : 'deposit_correction',
            'corrected_amount' => $request->amount,
            'correction_code' => $request->sandi_koreksi,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan koreksi berhasil dikirim. Menunggu persetujuan Supervisor sebelum saldo nasabah berubah.');
    }

    public function show($id)
    {
        return redirect()->route('teller.koreksi');
    }
}