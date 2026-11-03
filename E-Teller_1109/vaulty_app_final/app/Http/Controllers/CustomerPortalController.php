<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CustomerNotification;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerPortalController extends Controller
{
    private function account(): Account
    {
        return Account::where('user_id', auth()->id())->firstOrFail();
    }

    public function dashboard()
    {
        $account = $this->account();
        $recentTransactions = Transaction::where('account_id', $account->id)
            ->where('status', 'approved')->latest()->take(5)->get();
        $unread = CustomerNotification::where('user_id', auth()->id())->whereNull('read_at')->count();

        return view('customer.dashboard', compact('account', 'recentTransactions', 'unread'));
    }

    public function saldo()
    {
        $account = $this->account();
        return view('customer.saldo', compact('account'));
    }

    public function history(Request $request)
    {
        $account = $this->account();
        $query = Transaction::where('account_id', $account->id)->where('status', 'approved');
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->to);
        $transactions = $query->latest()->paginate(15)->withQueryString();
        return view('customer.history', compact('transactions'));
    }

    public function receipt(Transaction $transaction)
    {
        abort_unless($transaction->account->user_id === auth()->id() && $transaction->status === 'approved', 403);
        return view('customer.receipt', compact('transaction'));
    }

    public function qrScanner()
    {
        return view('customer.qr-scanner');
    }

    public function qrIdentity()
    {
        $account = $this->account();

        if (!$account->qr_token) {
            $account->update(['qr_token' => \Illuminate\Support\Str::random(48)]);
            $account->refresh();
        }

        $scanUrl = route('customer.qr-account', $account->qr_token);

        return view('customer.qr-account', compact('account', 'scanUrl'));
    }

    /**
     * QR identitas nasabah (dibuat Administrator) di-scan lewat aplikasi mobile nasabah.
     * Hasilnya: nasabah langsung masuk (login) ke akunnya sendiri tanpa mengetik
     * username/password. Aksesnya tetap terbatas read-only (cek saldo, mutasi,
     * notifikasi) sesuai hak akses Nasabah — penarikan dana tetap wajib verifikasi
     * PIN di loket Teller, jadi QR ini tidak bisa dipakai memindahkan dana.
     */
    public function scanAccount(string $token)
    {
        $account = Account::where('qr_token', $token)->with('user')->firstOrFail();

        if ($account->status !== 'active' || $account->user->status !== 'active') {
            abort(403, 'Rekening/akun nasabah sedang tidak aktif.');
        }

        Auth::login($account->user);

        return redirect()->route('customer.dashboard')->with('success', 'Berhasil masuk lewat QR identitas nasabah.');
    }

    public function notifications()
    {
        $notifications = CustomerNotification::where('user_id', auth()->id())->latest()->paginate(15);
        CustomerNotification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
        return view('customer.notifications', compact('notifications'));
    }
}
