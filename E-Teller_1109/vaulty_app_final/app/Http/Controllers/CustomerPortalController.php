<?php
/* Tujuan file: Controller ini menghubungkan permintaan halaman fitur customer portal controller dengan validasi, proses aplikasi, dan tampilan yang sesuai. */


namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CustomerNotification;
use App\Models\StatementRequest;
use App\Models\Transaction;
use App\Services\ReportExcelService;
use App\Services\SlipFormatter;
use App\Services\SlipPdfService;
use App\Services\StatementService;
use DomainException;
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

    /**
     * Bukti transaksi nasabah dalam format PDF (slip hanya tersedia sebagai PDF).
     */
    public function receiptPdf(Transaction $transaction, SlipPdfService $pdf)
    {
        abort_unless($transaction->account->user_id === auth()->id() && $transaction->status === 'approved', 403);

        $transaction->loadMissing(['account.user', 'teller']);
        $bytes = $pdf->render(SlipFormatter::detail($transaction));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="slip-'
                . ($transaction->reference_number ?: $transaction->id) . '.pdf"',
        ]);
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

    /**
     * Halaman mutasi nasabah: daftar permintaan cetak mutasi dan berkas PDF
     * yang sudah disiapkan Teller.
     */
    public function statements()
    {
        $account = $this->account();

        $requests = StatementRequest::where('account_id', $account->id)
            ->latest()
            ->paginate(10);

        return view('customer.mutasi', compact('account', 'requests'));
    }

    /** Nasabah mengajukan cetak mutasi dari portal web. */
    public function requestStatement(Request $request, StatementService $statements)
    {
        $data = $request->validate([
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'period_from.required' => 'Tanggal awal periode wajib diisi.',
            'period_to.required' => 'Tanggal akhir periode wajib diisi.',
            'period_from.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'period_to.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
        ]);

        $account = $this->account();

        try {
            $period = $statements->resolvePeriod($data['period_from'], $data['period_to']);
            $statementRequest = $statements->requestFromCustomer(
                auth()->user(),
                $account,
                $period['from'],
                $period['to'],
                $data['reason'] ?? null
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['mutasi' => $exception->getMessage()])->withInput();
        }

        return back()->with(
            'success',
            'Permintaan mutasi periode ' . $statementRequest->periodLabel() . ' (' . $statementRequest->request_code
                . ') dikirim ke petugas. Anda akan menerima notifikasi saat berkas PDF siap diunduh.'
        );
    }

    /** Unduh berkas PDF mutasi milik nasabah yang sedang login. */
    public function downloadStatement(StatementRequest $statement, StatementService $statements)
    {
        $account = $this->account();

        abort_unless(
            (int) $statement->account_id === (int) $account->id && $statement->isReady(),
            404
        );

        $bytes = $statements->pdfContents($statement);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $statement->fileName() . '"',
        ]);
    }

    /** Unduh mutasi rekening nasabah dalam format Excel. */
    public function downloadStatementExcel(
        StatementRequest $statement,
        StatementService $statements,
        ReportExcelService $excel
    ) {
        $account = $this->account();

        abort_unless(
            (int) $statement->account_id === (int) $account->id && $statement->isReady(),
            404
        );

        $data = $statements->build($account, $statement->period_from, $statement->period_to);

        return $excel->download(
            preg_replace('/\.pdf$/', '.xlsx', $statement->fileName()),
            $statements->excelSheets($data)
        );
    }
}
