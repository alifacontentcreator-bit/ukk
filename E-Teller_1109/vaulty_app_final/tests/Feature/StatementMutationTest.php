<?php
/* Tujuan file: Pengujian ini memeriksa fitur mutasi rekening: cetak mutasi Teller
   (pratinjau, PDF, Excel) dan alur permintaan mutasi nasabah dari aplikasi mobile. */

namespace Tests\Feature;

use App\Models\Account;
use App\Models\StatementRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatementMutationTest extends TestCase
{
    use RefreshDatabase;

    private User $teller;
    private User $customer;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->teller = User::create([
            'name' => 'Budi Teller',
            'username' => 'budi-mutasi',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Irfan Cahyo',
            'username' => '17750',
            'nis' => '17750',
            'kelas' => 'XI TKJ 1',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->account = Account::create([
            'user_id' => $this->customer->id,
            'account_number' => 'TKJ-2026-01-02',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'pin_attempts' => 0,
            'pin_reset_required' => false,
            'pin_blocked' => false,
            'balance' => 45000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);
    }

    private function makeTransaction(
        string $type,
        float $amount,
        float $before,
        float $after,
        Carbon $at
    ): Transaction {
        return Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-' . strtoupper($type) . '-' . strtoupper(Str::random(8)),
            'reference_number' => 'REF' . strtoupper(Str::random(10)),
            'type' => $type,
            'amount' => $amount,
            'status' => 'success',
            'description' => $type === 'deposit' ? 'Setor Tunai' : 'Tarik Tunai',
            'approved_at' => $at,
            'data_before' => ['balance' => $before],
            'data_after' => ['balance' => $after],
        ]);
    }

    /** @return array<string, string> */
    private function statementQuery(string $from, string $to): array
    {
        return [
            'account_number' => $this->account->account_number,
            'from' => $from,
            'to' => $to,
        ];
    }

    public function test_teller_preview_shows_credit_debit_and_ending_balance(): void
    {
        $this->makeTransaction('deposit', 20000, 30000, 50000, now()->subDays(2));
        $this->makeTransaction('withdrawal', 5000, 50000, 45000, now()->subDay());

        $this->actingAs($this->teller)
            ->get(route('teller.mutasi', $this->statementQuery(
                now()->subDays(3)->toDateString(),
                now()->toDateString()
            )))
            ->assertOk()
            ->assertSee('Mutasi Rekening TKJ-2026-01-02')
            ->assertSee('Irfan Cahyo')
            ->assertSee('Setor Tunai')
            ->assertSee('Tarik Tunai')
            ->assertSee('Tanggal transaksi')
            ->assertSee('Mutasi debit (DB)')
            ->assertSee('Mutasi kredit (CR)')
            ->assertSee('Saldo akhir')
            ->assertSee('Rp 30.000')
            ->assertSee('Rp 20.000')
            ->assertSee('Rp 5.000')
            ->assertSee('Rp 45.000');
    }

    public function test_teller_can_download_statement_as_pdf_and_excel(): void
    {
        $this->makeTransaction('deposit', 20000, 30000, 50000, now()->subDay());

        $query = $this->statementQuery(now()->subDays(3)->toDateString(), now()->toDateString());

        $pdf = $this->actingAs($this->teller)->get(route('teller.mutasi.pdf', $query));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');

        $bytes = $pdf->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $bytes);
        $this->assertStringContainsString('MUTASI REKENING', $bytes);
        $this->assertStringContainsString('Saldo Akhir', $bytes);
        $this->assertStringContainsString('Setor Tunai', $bytes);
        $this->assertStringEndsWith('%%EOF', $bytes);

        $excel = $this->actingAs($this->teller)->get(route('teller.mutasi.excel', $query));
        $excel->assertOk();
        $excel->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        // Berkas .xlsx adalah arsip ZIP.
        $this->assertStringStartsWith('PK', $excel->streamedContent());
    }

    public function test_customer_requests_statement_and_receives_pdf_after_teller_sends(): void
    {
        $this->makeTransaction('deposit', 10000, 35000, 45000, now()->subDay());

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/statements', [
                'period_from' => now()->subDays(7)->toDateString(),
                'period_to' => now()->toDateString(),
                'reason' => 'Verifikasi beasiswa',
            ])
            ->assertCreated()
            ->assertJsonPath('code', 'STATEMENT_REQUEST_SENT')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_download', false)
            ->assertJsonPath('data.download_url', null);

        $requestId = $response->json('data.id');
        $statementRequest = StatementRequest::findOrFail($requestId);

        $this->assertSame('pending', $statementRequest->status);
        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $this->customer->id,
            'type' => 'statement',
            'title' => 'Permintaan mutasi diterima',
        ]);
        $this->assertDatabaseHas('report_notifications', [
            'user_id' => $this->teller->id,
            'type' => 'statement',
        ]);

        // Permintaan tampil di halaman Cetak Mutasi Teller untuk diproses.
        $this->actingAs($this->teller)
            ->get(route('teller.mutasi'))
            ->assertOk()
            ->assertSee('Permintaan Mutasi dari Aplikasi Nasabah')
            ->assertSee($statementRequest->request_code)
            ->assertSee('Kirim PDF');

        // Satu permintaan pending saja per rekening.
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/statements', [
                'period_from' => now()->startOfMonth()->toDateString(),
                'period_to' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'STATEMENT_REQUEST_REJECTED');

        // Berkas belum tersedia sebelum diproses Teller.
        $this->actingAs($this->customer, 'sanctum')
            ->get('/api/v1/statements/' . $requestId . '/pdf')
            ->assertStatus(409)
            ->assertJsonPath('code', 'STATEMENT_NOT_READY');

        // Teller memproses & mengirim berkas ke aplikasi nasabah.
        $this->actingAs($this->teller)
            ->post(route('teller.mutasi.kirim', $statementRequest))
            ->assertRedirect();

        $statementRequest->refresh();
        $this->assertSame('ready', $statementRequest->status);
        $this->assertNotNull($statementRequest->file_path);
        Storage::disk('local')->assertExists($statementRequest->file_path);

        $this->assertDatabaseHas('customer_notifications', [
            'user_id' => $this->customer->id,
            'title' => 'Mutasi rekening siap diunduh',
        ]);

        $notifications = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Mutasi rekening siap diunduh')
            ->assertJsonPath('data.0.type', 'statement')
            ->assertJsonPath('data.0.is_read', false);

        $this->assertGreaterThanOrEqual(2, $notifications->json('meta.unread_count'));

        $list = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/statements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $statementRequest->id)
            ->assertJsonPath('data.0.status', 'ready')
            ->assertJsonPath('data.0.can_download', true);

        $this->assertSame(
            route('api.statements.pdf', $statementRequest),
            $list->json('data.0.download_url')
        );

        $this->assertSame(
            route('api.statements.excel', $statementRequest),
            $list->json('data.0.excel_url')
        );

        $excelHeader = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $this->actingAs($this->customer)
            ->get(route('customer.mutasi.excel', $statementRequest))
            ->assertOk()
            ->assertHeader('Content-Type', $excelHeader);

        $this->actingAs($this->customer, 'sanctum')
            ->get('/api/v1/statements/' . $requestId . '/excel')
            ->assertOk()
            ->assertHeader('Content-Type', $excelHeader);

        $pdf = $this->actingAs($this->customer, 'sanctum')
            ->get('/api/v1/statements/' . $requestId . '/pdf');

        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('MUTASI REKENING', $pdf->getContent());
    }

    public function test_other_customer_cannot_open_statement_request(): void
    {
        $other = User::create([
            'name' => 'Nasabah Lain',
            'username' => '18880',
            'nis' => '18880',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        Account::create([
            'user_id' => $other->id,
            'account_number' => 'TKJ-2026-01-09',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'balance' => 0,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        $statementRequest = StatementRequest::create([
            'request_code' => 'MUT-TEST-0001',
            'account_id' => $this->account->id,
            'user_id' => $this->customer->id,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->toDateString(),
            'status' => StatementRequest::STATUS_PENDING,
        ]);

        $this->actingAs($other, 'sanctum')
            ->getJson('/api/v1/statements/' . $statementRequest->id)
            ->assertNotFound();

        $this->actingAs($other, 'sanctum')
            ->get('/api/v1/statements/' . $statementRequest->id . '/pdf')
            ->assertNotFound();
    }

    public function test_statement_range_is_limited_to_twelve_months(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/statements', [
                'period_from' => now()->subDays(500)->toDateString(),
                'period_to' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'STATEMENT_REQUEST_REJECTED');
    }

    public function test_long_statement_pdf_is_paginated_with_valid_xref(): void
    {
        $balance = 100000.0;

        for ($i = 0; $i < 55; $i++) {
            $before = $balance;
            $balance += 1000;
            $this->makeTransaction('deposit', 1000, $before, $balance, now()->subMinutes(55 - $i));
        }

        $response = $this->actingAs($this->teller)->get(route('teller.mutasi.pdf', $this->statementQuery(
            now()->subDay()->toDateString(),
            now()->toDateString()
        )));

        $response->assertOk();
        $bytes = $response->getContent();

        // 20 baris halaman pertama + 29 baris halaman berikutnya => 3 halaman.
        $this->assertStringContainsString('/Count 3', $bytes);
        $this->assertStringContainsString('Halaman 3 dari 3', $bytes);

        // Semua offset pada tabel xref harus menunjuk ke objek yang benar.
        $xrefPosition = (int) trim(substr($bytes, strrpos($bytes, 'startxref') + 9));
        $this->assertSame('xref', substr($bytes, $xrefPosition, 4));

        $this->assertSame(
            1,
            preg_match('/xref\s+0 (\d+)/', substr($bytes, $xrefPosition), $matches)
        );
        $objectCount = (int) $matches[1];
        $lines = preg_split('/\r?\n/', substr($bytes, $xrefPosition));

        for ($id = 1; $id < $objectCount; $id++) {
            $offset = (int) substr(trim($lines[$id + 2] ?? ''), 0, 10);
            $this->assertStringStartsWith(
                $id . ' 0 obj',
                substr($bytes, $offset, strlen((string) $id) + 6),
                "Offset xref untuk objek {$id} salah."
            );
        }
    }
}
