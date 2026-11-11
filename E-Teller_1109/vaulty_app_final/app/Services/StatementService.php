<?php
/* Tujuan file: Service ini menghitung isi mutasi rekening (kredit, debit, saldo berjalan) dan mengelola
   permintaan mutasi nasabah: pengajuan, penerbitan berkas PDF, penolakan, dan kedaluwarsa. */

namespace App\Services;

use App\Models\Account;
use App\Models\CustomerNotification;
use App\Models\StatementRequest;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class StatementService
{
    /**
     * Waktu efektif transaksi: saat DISETUJUI (saldo berubah), transaksi lama
     * memakai created_at. Sama dengan yang dipakai riwayat transaksi mobile.
     */
    private const EFFECTIVE_TIME = 'COALESCE(approved_at, created_at)';

    /** Status transaksi yang benar-benar mengubah saldo rekening. */
    private const FINAL_STATUSES = ['approved', 'success'];

    /** Batas rentang mutasi yang dapat dicetak (12 bulan). */
    public const MAX_RANGE_DAYS = 366;

    /** Permintaan nasabah kedaluwarsa setelah 7 hari tidak diproses. */
    public const REQUEST_TTL_DAYS = 7;

    /** Folder penyimpanan PDF mutasi pada disk "local" (storage/app/private). */
    private const STORAGE_DIR = 'statements';

    public function maxRangeDays(): int
    {
        return self::MAX_RANGE_DAYS;
    }

    /**
     * Menentukan periode cetak. Default: bulan berjalan sampai hari ini.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolvePeriod(?string $from, ?string $to): array
    {
        $toDate = $to ? Carbon::createFromFormat('Y-m-d', $to)->startOfDay() : today();
        $fromDate = $from ? Carbon::createFromFormat('Y-m-d', $from)->startOfDay() : $toDate->copy()->startOfMonth();

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy(), $fromDate->copy()];
        }

        return ['from' => $fromDate, 'to' => $toDate];
    }

    /**
     * Memastikan rentang tanggal masuk akal (urutan, bukan masa depan, maksimal 12 bulan).
     */
    public function assertRangeAllowed(CarbonInterface $from, CarbonInterface $to): void
    {
        if ($from->gt($to)) {
            throw new DomainException('Tanggal awal tidak boleh melebihi tanggal akhir.');
        }

        if ($to->gt(today()->endOfDay())) {
            throw new DomainException('Tanggal akhir mutasi tidak boleh melebihi hari ini.');
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw new DomainException(
                'Rentang mutasi maksimal ' . self::MAX_RANGE_DAYS . ' hari (12 bulan). Persempit periode lalu coba lagi.'
            );
        }
    }

    /**
     * Menyusun data mutasi rekening untuk periode tertentu.
     *
     * Baris memuat: tanggal + jam transaksi, keterangan, nomor referensi,
     * mutasi debit (DB), mutasi kredit (CR), dan saldo setelah transaksi.
     */
    public function build(Account $account, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $transactions = Transaction::where('account_id', $account->id)
            ->whereIn('status', self::FINAL_STATUSES)
            ->whereRaw('DATE(' . self::EFFECTIVE_TIME . ') BETWEEN ? AND ?', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->orderByRaw(self::EFFECTIVE_TIME . ' ASC')
            ->orderBy('id')
            ->get();

        $openingBalance = $this->openingBalance($account, $start, $transactions);
        $running = $openingBalance;
        $rows = [];
        $totalCredit = 0.0;
        $totalDebit = 0.0;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            $isCredit = $transaction->type === 'deposit';
            $at = $transaction->approved_at ?? $transaction->created_at;

            // Saldo setelah transaksi diambil dari catatan posting; bila kosong dihitung berurutan.
            $balanceAfter = data_get($transaction->data_after, 'balance');
            $running = $balanceAfter !== null
                ? (float) $balanceAfter
                : ($isCredit ? $running + $amount : $running - $amount);

            if ($isCredit) {
                $totalCredit += $amount;
            } else {
                $totalDebit += $amount;
            }

            $rows[] = [
                'date' => $at?->format('d/m/Y') ?? '-',
                'time' => $at?->format('H:i') ?? '-',
                'description' => $transaction->description
                    ?: ($isCredit ? 'Setor Tunai' : 'Tarik Tunai'),
                'reference' => $transaction->reference_number ?: $transaction->transaction_code,
                'debit' => $isCredit ? 0.0 : $amount,
                'credit' => $isCredit ? $amount : 0.0,
                'balance' => $running,
            ];
        }

        return [
            'account_number' => $account->account_number,
            'customer_name' => $account->user?->name ?? '-',
            'customer_class' => $account->user?->kelas,
            'product_type' => $account->product_type === 'tabungan_karyawan'
                ? 'Tabungan Guru/Karyawan'
                : 'Tabungan Siswa',
            'period_from' => $start->format('d/m/Y'),
            'period_to' => $end->format('d/m/Y'),
            'period_from_iso' => $start->toDateString(),
            'period_to_iso' => $end->toDateString(),
            'opening_balance' => $openingBalance,
            'closing_balance' => $rows ? $rows[count($rows) - 1]['balance'] : $openingBalance,
            'total_credit' => $totalCredit,
            'total_debit' => $totalDebit,
            'total_transactions' => count($rows),
            'rows' => $rows,
            'generated_at' => now(),
        ];
    }

    /**
     * Saldo sebelum transaksi pertama pada periode.
     *
     * Urutan sumber: saldo akhir transaksi terakhir sebelum periode, lalu
     * saldo sebelum transaksi pertama di periode, terakhir dihitung mundur
     * dari saldo rekening saat ini.
     */
    private function openingBalance(Account $account, Carbon $start, $transactions): float
    {
        $previous = Transaction::where('account_id', $account->id)
            ->whereIn('status', self::FINAL_STATUSES)
            ->whereRaw('DATE(' . self::EFFECTIVE_TIME . ') < ?', [$start->toDateString()])
            ->orderByRaw(self::EFFECTIVE_TIME . ' DESC')
            ->orderByDesc('id')
            ->first();

        if ($previous) {
            $previousBalance = data_get($previous->data_after, 'balance');
            if ($previousBalance !== null) {
                return (float) $previousBalance;
            }
        }

        $first = $transactions->first();
        if ($first) {
            $beforeBalance = data_get($first->data_before, 'balance');
            if ($beforeBalance !== null) {
                return (float) $beforeBalance;
            }
        }

        $net = Transaction::where('account_id', $account->id)
            ->whereIn('status', self::FINAL_STATUSES)
            ->whereRaw('DATE(' . self::EFFECTIVE_TIME . ') >= ?', [$start->toDateString()])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) AS net")
            ->value('net');

        return round((float) $account->balance - (float) $net, 2);
    }

    /**
     * Nasabah mengajukan mutasi dari aplikasi mobile / portal nasabah.
     */
    public function requestFromCustomer(
        User $customer,
        Account $account,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $reason = null
    ): StatementRequest {
        $this->assertRangeAllowed($from, $to);

        $pending = StatementRequest::where('account_id', $account->id)
            ->open()
            ->exists();

        if ($pending) {
            throw new DomainException(
                'Permintaan mutasi Anda masih menunggu diproses petugas. Silakan tunggu notifikasi berikutnya.'
            );
        }

        $request = StatementRequest::create([
            'request_code' => StatementRequest::generateCode(),
            'account_id' => $account->id,
            'user_id' => $customer->id,
            'period_from' => Carbon::parse($from)->toDateString(),
            'period_to' => Carbon::parse($to)->toDateString(),
            'status' => StatementRequest::STATUS_PENDING,
            'note' => $reason ?: null,
            'expires_at' => now()->addDays(self::REQUEST_TTL_DAYS),
        ]);

        CustomerNotification::create([
            'user_id' => $customer->id,
            'title' => 'Permintaan mutasi diterima',
            'message' => 'Permintaan cetak mutasi periode ' . $request->periodLabel()
                . ' (' . $request->request_code . ') sudah diterima. Petugas akan menyiapkan berkas PDF; '
                . 'Anda akan menerima notifikasi saat berkas siap diunduh.',
            'type' => 'statement',
        ]);

        // Notifikasi internal tidak boleh menggagalkan pengajuan nasabah;
        // pada server yang belum memiliki PetugasNotifier pengajuan tetap
        // tersimpan dan muncul di daftar permintaan Teller.
        if (class_exists(PetugasNotifier::class)) {
            try {
                app(PetugasNotifier::class)->kirimKeRole(
                    'teller',
                    'Permintaan mutasi nasabah',
                    'Nasabah ' . ($customer->name ?? '-') . ' (' . $account->account_number . ') meminta mutasi periode '
                        . $request->periodLabel() . '. Buka menu Cetak Mutasi untuk memproses.',
                    'statement'
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $request;
    }

    /**
     * Teller menerbitkan PDF mutasi lalu mengirimkannya ke nasabah.
     */
    public function markReady(StatementRequest $request, User $teller): StatementRequest
    {
        if ($request->status === StatementRequest::STATUS_READY) {
            return $request;
        }

        if ($request->status !== StatementRequest::STATUS_PENDING) {
            throw new DomainException('Permintaan mutasi ini sudah tidak dapat diproses (status: '
                . $request->statusLabel() . ').');
        }

        if ($request->isExpired()) {
            $this->expire($request);
            throw new DomainException('Permintaan mutasi sudah kedaluwarsa. Minta nasabah mengajukan ulang.');
        }

        $account = $request->account;
        if (!$account) {
            throw new DomainException('Rekening nasabah untuk permintaan ini sudah tidak tersedia.');
        }

        $statement = $this->build($account, $request->period_from, $request->period_to);
        $path = $this->storePdf($request, $statement);

        $request->update([
            'status' => StatementRequest::STATUS_READY,
            'file_path' => $path,
            'note' => null,
            'processed_by' => $teller->id,
            'processed_at' => now(),
            'notified_at' => now(),
        ]);

        CustomerNotification::create([
            'user_id' => $request->user_id,
            'title' => 'Mutasi rekening siap diunduh',
            'message' => 'Berkas PDF mutasi periode ' . $request->periodLabel()
                . ' (' . $request->request_code . ') sudah tersedia. Buka menu Mutasi untuk melihat dan mengunduh berkas.',
            'type' => 'statement',
        ]);

        return $request->refresh();
    }

    /**
     * Teller menolak permintaan mutasi (misal periode tidak valid atau data tidak ditemukan).
     */
    public function reject(StatementRequest $request, User $teller, string $reason): StatementRequest
    {
        if ($request->status !== StatementRequest::STATUS_PENDING) {
            throw new DomainException('Permintaan mutasi ini sudah tidak dapat ditolak (status: '
                . $request->statusLabel() . ').');
        }

        $request->update([
            'status' => StatementRequest::STATUS_REJECTED,
            'note' => $reason,
            'processed_by' => $teller->id,
            'processed_at' => now(),
            'notified_at' => now(),
        ]);

        if ($request->user_id) {
            CustomerNotification::create([
                'user_id' => $request->user_id,
                'title' => 'Permintaan mutasi ditolak',
                'message' => 'Permintaan mutasi periode ' . $request->periodLabel()
                    . ' ditolak petugas. Alasan: ' . $reason,
                'type' => 'statement',
            ]);
        }

        return $request->refresh();
    }

    /**
     * Menandai permintaan pending yang melewati batas waktu menjadi kedaluwarsa.
     */
    public function expireStale(): void
    {
        $stale = StatementRequest::where('status', StatementRequest::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($stale as $request) {
            $this->expire($request);
        }
    }

    private function expire(StatementRequest $request): void
    {
        $request->update([
            'status' => StatementRequest::STATUS_EXPIRED,
            'processed_at' => now(),
        ]);

        if ($request->user_id) {
            CustomerNotification::create([
                'user_id' => $request->user_id,
                'title' => 'Permintaan mutasi kedaluwarsa',
                'message' => 'Permintaan mutasi periode ' . $request->periodLabel()
                    . ' tidak diproses dalam ' . self::REQUEST_TTL_DAYS . ' hari. Silakan ajukan kembali.',
                'type' => 'statement',
            ]);
        }
    }

    /**
     * Mengambil isi PDF mutasi. Berkas tersimpan dipakai lebih dulu supaya
     * dokumen yang sudah dikirim ke nasabah tidak berubah.
     */
    public function pdfContents(StatementRequest $request): string
    {
        $path = $request->file_path;

        if ($path && Storage::disk('local')->exists($path)) {
            $contents = Storage::disk('local')->get($path);
            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        }

        $account = $request->account;
        if (!$account) {
            throw new DomainException('Rekening nasabah untuk permintaan ini sudah tidak tersedia.');
        }

        $statement = $this->build($account, $request->period_from, $request->period_to);
        $contents = app(StatementPdfService::class)->render($statement, $request->request_code);

        if ($path) {
            Storage::disk('local')->put($path, $contents);
        }

        return $contents;
    }

    /**
     * Baris Excel mutasi rekening (satu sheet siap unduh).
     *
     * @return array<int, array<string, mixed>>
     */
    public function excelSheets(array $statement): array
    {
        $rows = [
            ['MUTASI REKENING - LKM MITRA SISWA ABADI (BANK MINI)'],
            ['No. Rekening', $statement['account_number']],
            ['Nama Nasabah', $statement['customer_name']],
            ['Periode', $statement['period_from'] . ' s/d ' . $statement['period_to']],
            ['Saldo Awal', (float) $statement['opening_balance']],
            ['Total Mutasi Kredit (CR)', (float) $statement['total_credit']],
            ['Total Mutasi Debit (DB)', (float) $statement['total_debit']],
            ['Saldo Akhir', (float) $statement['closing_balance']],
            [],
            [
                'Tanggal Transaksi',
                'Keterangan / Deskripsi Transaksi',
                'Referensi',
                'Mutasi Debit (DB)',
                'Mutasi Kredit (CR)',
                'Saldo Akhir',
            ],
        ];

        foreach ($statement['rows'] as $row) {
            $rows[] = [
                $row['date'] . ' ' . $row['time'],
                $row['description'],
                $row['reference'],
                $row['debit'] > 0 ? (float) $row['debit'] : null,
                $row['credit'] > 0 ? (float) $row['credit'] : null,
                (float) $row['balance'],
            ];
        }

        return [[
            'title' => 'Mutasi Rekening',
            'rows' => $rows,
            'header_row' => 10,
            'title_rows' => [1],
            'money_columns' => ['D', 'E', 'F'],
        ]];
    }

    private function storePdf(StatementRequest $request, array $statement): string
    {
        $path = self::STORAGE_DIR . '/' . $request->request_code . '.pdf';

        Storage::disk('local')->put(
            $path,
            app(StatementPdfService::class)->render($statement, $request->request_code)
        );

        return $path;
    }
}
