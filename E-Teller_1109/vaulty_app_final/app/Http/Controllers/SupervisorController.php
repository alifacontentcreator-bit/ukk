<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\AccountingJournal;
use App\Models\CustomerNotification;
use App\Models\SystemSetting;
use App\Models\TransactionCorrection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupervisorController extends Controller
{
    public function validation()
    {
        $transactions = Transaction::with(['account.user','teller'])->where('status','pending')->latest()->get();
        // Halaman ini hanya untuk sisa transaksi lama berstatus pending (alur sebelumnya).
        return view('supervisor.validasi', compact('transactions'));
    }

    public function approve($id)
    {
        return DB::transaction(function () use ($id) {
            $transaction = Transaction::lockForUpdate()->findOrFail($id);
            if ($transaction->status !== 'pending') {
                return back()->withErrors(['transaction' => 'Transaksi ini sudah diproses.']);
            }

            $account = Account::where('id', $transaction->account_id)->lockForUpdate()->firstOrFail();
            $before = (float) $account->balance;

            $minBalance = (int) (SystemSetting::where('key', 'min_balance')->value('value') ?: 10000);

            if ($transaction->type === 'withdrawal' && ($account->balance - $transaction->amount) < $minBalance) {
                $transaction->update([
                    'status' => 'rejected', 'approved_by' => auth()->id(), 'approved_at' => now(),
                    'rejected_reason' => 'Saldo tidak mencukupi saat proses validasi (sisa saldo wajib minimal Rp ' . number_format($minBalance, 0, ',', '.') . ').'
                ]);
                CustomerNotification::create([
                    'user_id' => $account->user_id,
                    'title' => 'Transaksi ditolak',
                    'message' => 'Transaksi ' . $transaction->reference_number . ' ditolak karena saldo tidak mencukupi.',
                    'type' => 'transaction',
                    'transaction_id' => $transaction->id,
                ]);
                return back()->withErrors(['transaction' => 'Transaksi ditolak karena saldo tidak mencukupi.']);
            }

            if ($transaction->type === 'deposit') $account->balance += $transaction->amount;
            if ($transaction->type === 'withdrawal') $account->balance -= $transaction->amount;
            $account->save();

            $transaction->update([
                'status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(),
                'data_after' => ['balance' => (float) $account->balance],
                'data_before' => $transaction->data_before ?: ['balance' => $before],
            ]);

            if ($transaction->type === 'deposit') {
                AccountingJournal::create(['transaction_id' => $transaction->id, 'account_code' => '111', 'position' => 'debit', 'amount' => $transaction->amount]);
                AccountingJournal::create(['transaction_id' => $transaction->id, 'account_code' => '211', 'position' => 'credit', 'amount' => $transaction->amount]);
            } else {
                AccountingJournal::create(['transaction_id' => $transaction->id, 'account_code' => '211', 'position' => 'debit', 'amount' => $transaction->amount]);
                AccountingJournal::create(['transaction_id' => $transaction->id, 'account_code' => '111', 'position' => 'credit', 'amount' => $transaction->amount]);
            }

            CustomerNotification::create([
                'user_id' => $account->user_id,
                'title' => $transaction->type === 'deposit' ? 'Setoran berhasil' : 'Penarikan berhasil',
                'message' => ($transaction->type === 'deposit' ? 'Setoran' : 'Penarikan').' sebesar Rp '.number_format($transaction->amount, 0, ',', '.').' telah disetujui. Ref: '.$transaction->reference_number.'.',
                'type' => 'transaction',
                'transaction_id' => $transaction->id,
            ]);

            return back()->with('success', 'Transaksi '.$transaction->reference_number.' berhasil disetujui.');
        });
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);
        $transaction = Transaction::findOrFail($id);
        if ($transaction->status !== 'pending') return back()->withErrors(['transaction' => 'Transaksi ini sudah diproses.']);
        $transaction->update([
            'status' => 'rejected','approved_by' => auth()->id(),'approved_at' => now(),'rejected_reason' => $request->reason,
        ]);
        CustomerNotification::create([
            'user_id' => $transaction->account->user_id,
            'title' => 'Transaksi ditolak',
            'message' => 'Transaksi '.$transaction->reference_number.' ditolak. Alasan: '.$request->reason,
            'type' => 'transaction',
            'transaction_id' => $transaction->id,
        ]);
        return back()->with('success', 'Transaksi '.$transaction->reference_number.' ditolak.');
    }

    // ============================================================
    // VALIDASI KOREKSI TRANSAKSI (Teller mengajukan, Supervisor approve satu-satu)
    // ============================================================

    public function koreksiIndex(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,all'],
            'teller_id' => ['nullable', 'integer', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $status = $f['status'] ?? 'pending';
        $query = TransactionCorrection::with(['transaction.account.user', 'teller', 'supervisor']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (!empty($f['teller_id'])) {
            $query->where('teller_id', $f['teller_id']);
        }
        if (!empty($f['q'])) {
            $q = trim($f['q']);
            $query->where(function ($w) use ($q) {
                $w->where('correction_code', 'like', "%{$q}%")
                    ->orWhere('reason', 'like', "%{$q}%")
                    ->orWhereHas('transaction', function ($t) use ($q) {
                        $t->where('transaction_code', 'like', "%{$q}%")
                            ->orWhereHas('account', function ($a) use ($q) {
                                $a->where('account_number', 'like', "%{$q}%")
                                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"));
                            });
                    });
            });
        }

        $counts = TransactionCorrection::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $corrections = $query->latest()->paginate(15)->withQueryString();
        $tellers = User::where('role', 'teller')->orderBy('name')->get();

        return view('supervisor.koreksi', compact('corrections', 'status', 'counts', 'tellers'));
    }

    public function koreksiApprove($id)
    {
        return DB::transaction(function () use ($id) {
            $correction = TransactionCorrection::lockForUpdate()->findOrFail($id);

            if ($correction->status !== 'pending') {
                return back()->withErrors(['correction' => 'Pengajuan koreksi ini sudah diproses.']);
            }

            $wrongTx = Transaction::findOrFail($correction->transaction_id);
            $account = Account::where('id', $wrongTx->account_id)->lockForUpdate()->firstOrFail();

            $minBalance = (int) (SystemSetting::where('key', 'min_balance')->value('value') ?: 10000);
            $isKurang = $correction->type === 'withdrawal_correction';

            if ($isKurang && ($account->balance - $correction->corrected_amount) < $minBalance) {
                $correction->update([
                    'status' => 'rejected',
                    'supervisor_id' => auth()->id(),
                ]);
                return back()->withErrors(['correction' => 'Koreksi ditolak otomatis: saldo sudah tidak mencukupi saat divalidasi.']);
            }

            if ($isKurang) {
                $account->balance -= $correction->corrected_amount;
                $type = 'withdrawal';
            } else {
                $account->balance += $correction->corrected_amount;
                $type = 'deposit';
            }
            $account->save();

            $koreksiTx = Transaction::create([
                'transaction_code' => 'KOR-' . strtoupper(Str::random(10)),
                'account_id' => $account->id,
                'user_id' => $correction->teller_id,
                'type' => $type,
                'amount' => $correction->corrected_amount,
                'status' => 'success',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'data_after' => ['balance' => (float) $account->balance],
                'description' => '[KOREKSI - ' . $correction->correction_code . '] ' . $correction->reason . ' (Ref: ' . $wrongTx->transaction_code . ')',
            ]);

            if ($type === 'deposit') {
                AccountingJournal::create(['transaction_id' => $koreksiTx->id, 'account_code' => '111', 'position' => 'debit', 'amount' => $correction->corrected_amount]);
                AccountingJournal::create(['transaction_id' => $koreksiTx->id, 'account_code' => '211', 'position' => 'credit', 'amount' => $correction->corrected_amount]);
            } else {
                AccountingJournal::create(['transaction_id' => $koreksiTx->id, 'account_code' => '211', 'position' => 'debit', 'amount' => $correction->corrected_amount]);
                AccountingJournal::create(['transaction_id' => $koreksiTx->id, 'account_code' => '111', 'position' => 'credit', 'amount' => $correction->corrected_amount]);
            }

            $correction->update([
                'status' => 'approved',
                'supervisor_id' => auth()->id(),
            ]);

            CustomerNotification::create([
                'user_id' => $account->user_id,
                'title' => 'Koreksi saldo disetujui',
                'message' => 'Saldo Anda disesuaikan sebesar Rp ' . number_format($correction->corrected_amount, 0, ',', '.') . ' (koreksi ' . $correction->correction_code . ').',
                'type' => 'transaction',
                'transaction_id' => $koreksiTx->id,
            ]);

            return back()->with('success', 'Koreksi disetujui. Saldo nasabah telah disesuaikan.');
        });
    }

    public function koreksiReject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);

        $correction = TransactionCorrection::findOrFail($id);
        if ($correction->status !== 'pending') {
            return back()->withErrors(['correction' => 'Pengajuan koreksi ini sudah diproses.']);
        }

        $correction->update([
            'status' => 'rejected',
            'supervisor_id' => auth()->id(),
            'reason' => $correction->reason . ' | Ditolak Supervisor: ' . $request->reason,
        ]);

        return back()->with('success', 'Pengajuan koreksi ditolak.');
    }
}