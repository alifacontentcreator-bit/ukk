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
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function create(Request $request, TransactionPostingService $posting)
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

        // Batas saldo mengendap dipakai untuk menampilkan sisa saldo yang boleh ditarik.
        $minBalance = $posting->minBalance();

        return view('teller.transaksi', compact(
            'nasabah',
            'account_number',
            'type',
            'qrDevice',
            'minBalance'
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
            'reference_number' => $approval->transaction?->reference_number
                ?? $approval->reference_number,
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
                $request->input('type') === 'deposit' ? 'min:5000' : 'gt:0',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255'
            ],
        ], [
            'amount.min' => 'Minimal setor tunai 5.000.',
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
             * PIN diblokir karena salah 3 kali: rekening memang berstatus
             * blocked, tetapi Teller perlu pesan yang mengarahkan nasabah
             * menyelesaikan pengajuan buka blokir + reset PIN.
             */
            if ($account->pin_blocked) {
                throw new DomainException(
                    'PIN nasabah diblokir karena salah 3 kali. Minta nasabah '
                    . 'menyelesaikan pengajuan buka blokir + reset PIN melalui aplikasi.'
                );
            }

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

            /*
             * PIN belum dibuat/diatur ulang membuat notifikasi konfirmasi tidak
             * akan bisa disetujui nasabah. Tolak lebih awal dengan pesan jelas.
             */
            if ($account->pin_reset_required || $account->pin_is_default) {
                throw new DomainException(
                    'PIN nasabah belum dibuat atau wajib diatur ulang. Minta nasabah '
                    . 'menyelesaikan pengaturan PIN melalui aplikasi.'
                );
            }

            /*
             * Penarikan tunai: pastikan sisa saldo setelah penarikan masih
             * memenuhi batas saldo mengendap. Pemeriksaan dilakukan sebelum
             * permintaan otorisasi PIN dikirim ke nasabah, supaya Teller tidak
             * meminta PIN untuk transaksi yang pasti ditolak.
             */
            if ($request->type === 'withdrawal') {
                $minBalance = $posting->minBalance();
                $balance = (float) $account->balance;
                $amount = (float) $request->amount;

                if (($balance - $amount) < $minBalance) {
                    $maxWithdrawal = max(0, $balance - $minBalance);

                    throw new DomainException(
                        'Transaksi gagal. Minimal saldo mengendap Rp ' .
                        number_format($minBalance, 0, ',', '.') . '. ' .
                        ($maxWithdrawal > 0
                            ? 'Maksimal penarikan untuk rekening ini Rp ' .
                                number_format($maxWithdrawal, 0, ',', '.') . '.'
                            : 'Saldo nasabah Rp ' . number_format($balance, 0, ',', '.') .
                                ' tidak dapat ditarik karena sudah menyentuh batas saldo mengendap.')
                    );
                }
            }

            /*
             * SETORAN dan PENARIKAN sama-sama menunggu konfirmasi PIN nasabah
             * di aplikasi mobile. Saldo baru berubah setelah nasabah menekan
             * "Konfirmasi Transaksi" dan PIN-nya benar.
             */
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
                'reference_number' => $this->generateReferenceNumber(),
                'type' => $request->type,
                'amount' => $request->amount,
                'description' => $request->description,
                'status' => 'pending',
                'expires_at' => now()->addMinutes(5),
            ]);

            DB::commit();

            $jenis = $request->type === 'deposit' ? 'Setoran' : 'Penarikan';

            return redirect()
                ->route('teller.transaksi.create', [
                    'account_number' => $account->account_number,
                    'type' => $request->type,
                ])
                ->with(
                    'success',
                    'Permintaan transaksi #' . $approval->id . ' (' . $jenis .
                    ' Rp ' . number_format((float) $request->amount, 0, ',', '.') .
                    ', Ref: ' . $approval->reference_number . ') dikirim ke aplikasi nasabah untuk konfirmasi PIN. ' .
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

    /**
     * Nomor referensi permintaan yang juga dipakai sebagai nomor referensi
     * transaksi setelah nasabah menyetujui dengan PIN, supaya Teller dan
     * nasabah melihat nomor yang sama pada notifikasi dan slip.
     */
    private function generateReferenceNumber(): string
    {
        do {
            $reference = 'REF' . now()->format('ymd') . strtoupper(Str::random(6));
        } while (
            Transaction::where('reference_number', $reference)->exists() ||
            CustomerTransactionApproval::where('reference_number', $reference)->exists()
        );

        return $reference;
    }
}