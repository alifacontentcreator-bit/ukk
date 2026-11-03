<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\CustomerTransactionApproval;
use App\Models\SystemSetting;
use App\Services\TransactionPostingService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function create(Request $request)
    {
        $account_number = $request->input('account_number');
        $type = $request->input('type', 'deposit');
        $nasabah = null;

        if ($account_number) {
            $nasabah = Account::with('user')
                ->where('account_number', $account_number)
                ->first();
        }

        $qrDevice = SystemSetting::where('key', 'qr_device')->value('value');
        if (!in_array($qrDevice, ['hid', 'bluetooth', 'camera'], true)) {
            $qrDevice = 'hid';
        }

        return view('teller.transaksi', compact(
            'nasabah',
            'account_number',
            'type',
            'qrDevice'
        ));
    }

    public function printSlip($id)
    {
        $transaction = Transaction::with('account.user')
            ->findOrFail($id);

        return view('teller.cetak-slip', compact('transaction'));
    }

    public function resolveAccountQr(string $token)
    {
        $account = Account::where('qr_token', $token)->firstOrFail();

        return response()->json([
            'account_number' => $account->account_number
        ]);
    }

    public function approvalStatus(CustomerTransactionApproval $approval)
    {
        abort_unless((int) $approval->teller_id === (int) auth()->id(), 404);

        if ($approval->status === 'pending' && $approval->expires_at->isPast()) {
            $approval->update([
                'status' => 'expired',
                'processed_at' => now(),
            ]);
        }

        return response()->json([
            'status' => $approval->status,
            'transaction_id' => $approval->transaction_id,
            'reference_number' => $approval->transaction?->reference_number,
            'slip_url' => $approval->transaction_id
                ? route('teller.transaksi.cetak', $approval->transaction_id)
                : null,
        ]);
    }

    public function store(
        Request $request,
        TransactionPostingService $posting
    ) {
        $request->validate([
            'account_number' => [
                'required',
                'string',
                'exists:accounts,account_number'
            ],
            'type' => [
                'required',
                'in:deposit,withdrawal'
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1000'
            ],
            'description' => [
                'nullable',
                'string',
                'max:255'
            ],
        ]);

        DB::beginTransaction();

        try {
            $teller = auth()->user();

            if (!$teller) {
                throw new DomainException(
                    'Sesi login tidak ditemukan. Silakan login kembali.'
                );
            }

            /*
             * Memastikan laporan harian masih terbuka.
             */
            $posting->assertDayOpen($teller);

            /*
             * Mengunci rekening nasabah.
             */
            $account = Account::where(
                'account_number',
                $request->account_number
            )
                ->lockForUpdate()
                ->firstOrFail();

            $account->load('user');

            /*
             * Memastikan rekening dan nasabah aktif.
             */
            if (
                $account->status !== 'active' ||
                !$account->user ||
                $account->user->status !== 'active'
            ) {
                throw new DomainException(
                    'Rekening nasabah sedang tidak aktif atau diblokir. Transaksi ditolak.'
                );
            }

            CustomerTransactionApproval::where('account_id', $account->id)
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => 'expired',
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);

            if (
                CustomerTransactionApproval::where('account_id', $account->id)
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->exists()
            ) {
                throw new DomainException(
                    'Masih ada permintaan transaksi yang menunggu PIN nasabah.'
                );
            }

            $approval = CustomerTransactionApproval::create([
                'account_id' => $account->id,
                'teller_id' => $teller->id,
                'type' => $request->type,
                'amount' => $request->amount,
                'description' => $request->description,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(5),
            ]);

            DB::commit();

            return redirect()
                ->route('teller.transaksi.create', [
                    'account_number' => $account->account_number,
                    'type' => $request->type,
                ])
                ->with(
                    'success',
                    'Permintaan transaksi #' . $approval->id .
                    ' dikirim ke aplikasi nasabah untuk otorisasi PIN. ' .
                    'Transaksi belum berhasil; tunggu persetujuan sebelum menyerahkan atau menerima uang.'
                )
                ->with('customer_transaction_approval_id', $approval->id);

        } catch (DomainException $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => $e->getMessage()
            ])->withInput();

        } catch (\Throwable $e) {
            DB::rollBack();

            report($e);

            return back()->withErrors([
                'error' => 'Terjadi kesalahan sistem saat memproses transaksi. Silakan periksa kembali data atau hubungi administrator.'
            ])->withInput();
        }
    }
}