<?php
/* Tujuan file: Pengujian ini memastikan semua laporan (laporan harian, jurnal akuntansi,
   jurnal audit, log audit) dapat diunduh sebagai PDF dan Excel, sedangkan slip hanya PDF. */

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingJournal;
use App\Models\AuditLog;
use App\Models\DailyReport;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $supervisor;
    private User $teller;
    private User $customer;
    private Account $account;
    private DailyReport $report;
    private Transaction $deposit;
    private Transaction $withdrawal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Laporan',
            'username' => 'admin-laporan',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->supervisor = User::create([
            'name' => 'Supervisor Laporan',
            'username' => 'spv-laporan',
            'password' => 'password',
            'role' => 'supervisor',
            'status' => 'active',
        ]);

        $this->teller = User::create([
            'name' => 'Teller Laporan',
            'username' => 'teller-laporan',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $this->customer = User::create([
            'name' => 'Nasabah Laporan',
            'username' => '18801',
            'nis' => '18801',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->account = Account::create([
            'user_id' => $this->customer->id,
            'account_number' => 'LAP-2026-01-01',
            'pin' => bcrypt('246810'),
            'pin_is_default' => false,
            'balance' => 245000,
            'product_type' => 'tabungan_siswa',
            'status' => 'active',
        ]);

        $this->deposit = $this->makeTransaction('deposit', 125000, 'REFLAPORAN001');
        $this->withdrawal = $this->makeTransaction('withdrawal', 30000, 'REFLAPORAN002');

        $this->report = DailyReport::create([
            'teller_id' => $this->teller->id,
            'report_date' => today()->toDateString(),
            'saldo_awal' => 150000,
            'pecahan_100k' => 2,
            'pecahan_20k' => 2,
            'pecahan_5k' => 1,
            'deposit_total' => 125000,
            'withdrawal_total' => 30000,
            'transaction_count' => 2,
            'expected_cash' => 95000,
            'status' => 'approved',
            'approved_by' => $this->supervisor->id,
            'approved_at' => now(),
            'submitted_at' => now(),
            'transfer_status' => 'pending',
        ]);

        foreach ([[$this->deposit, '111', 'debit'], [$this->deposit, '211', 'credit'],
                  [$this->withdrawal, '211', 'debit'], [$this->withdrawal, '111', 'credit']] as [$trx, $code, $position]) {
            AccountingJournal::create([
                'transaction_id' => $trx->id,
                'account_code' => $code,
                'position' => $position,
                'amount' => $trx->amount,
            ]);
        }

        TransactionActivityLogger::berhasil($this->teller->id, [
            'type' => 'deposit',
            'amount' => 125000,
            'account_number' => $this->account->account_number,
            'reference_number' => 'REFLAPORAN001',
            'nasabah' => $this->customer->name,
        ], 'Setoran berhasil diposting.');
    }

    private function makeTransaction(string $type, float $amount, string $reference): Transaction
    {
        return Transaction::create([
            'account_id' => $this->account->id,
            'user_id' => $this->teller->id,
            'transaction_code' => 'TRX-' . strtoupper(Str::random(8)),
            'reference_number' => $reference,
            'type' => $type,
            'amount' => $amount,
            'status' => 'approved',
            'description' => $type === 'deposit' ? 'Setor Tunai' : 'Tarik Tunai',
            'approved_at' => now(),
            'data_after' => ['balance' => 245000],
        ]);
    }

    private function assertPdf($response, string $expectedText): void
    {
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $bytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $bytes);
        $this->assertStringContainsString($expectedText, $bytes);
        $this->assertStringEndsWith('%%EOF', $bytes);
    }

    private function assertExcel($response): void
    {
        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_admin_journal_exports_as_pdf_and_excel(): void
    {
        $this->assertPdf(
            $this->actingAs($this->admin)->get(route('admin.jurnal.pdf')),
            'LAPORAN JURNAL AKUNTANSI'
        );

        $this->assertExcel(
            $this->actingAs($this->admin)->get(route('admin.jurnal.excel'))
        );
    }

    public function test_supervisor_audit_journal_exports_as_pdf_and_excel(): void
    {
        $this->assertPdf(
            $this->actingAs($this->supervisor)->get(route('supervisor.jurnal.pdf')),
            'JURNAL AUDIT TRANSAKSI'
        );

        $this->assertExcel(
            $this->actingAs($this->supervisor)->get(route('supervisor.jurnal.excel'))
        );
    }

    public function test_daily_report_and_cash_recap_export_as_pdf_and_excel(): void
    {
        $pdf = $this->actingAs($this->supervisor)
            ->get(route('supervisor.laporan.pdf', $this->report));

        $this->assertPdf($pdf, 'LAPORAN HARIAN TELLER');
        $this->assertStringContainsString('Rekapitulasi Kas', $pdf->getContent());

        $this->assertExcel(
            $this->actingAs($this->supervisor)->get(route('supervisor.laporan.excel', $this->report))
        );

        // Teller juga dapat mengunduh laporannya sendiri.
        $this->assertPdf(
            $this->actingAs($this->teller)->get(route('teller.laporan.pdf', $this->report)),
            'LAPORAN HARIAN TELLER'
        );

        $this->assertExcel(
            $this->actingAs($this->teller)->get(route('teller.laporan.excel', $this->report))
        );

        $tanggal = ['tanggal' => today()->toDateString()];

        $this->assertPdf(
            $this->actingAs($this->supervisor)->get(route('supervisor.laporan.cetak-pdf', $tanggal)),
            'REKAP KAS HARIAN BANK MINI'
        );

        $this->assertExcel(
            $this->actingAs($this->supervisor)->get(route('supervisor.laporan.cetak-excel', $tanggal))
        );
    }

    public function test_teller_cannot_export_another_tellers_report(): void
    {
        $otherTeller = User::create([
            'name' => 'Teller Lain',
            'username' => 'teller-lain',
            'password' => 'password',
            'role' => 'teller',
            'status' => 'active',
        ]);

        $this->actingAs($otherTeller)
            ->get(route('teller.laporan.pdf', $this->report))
            ->assertForbidden();
    }

    public function test_teller_daily_report_excel_contains_rekap_rincian_and_transaksi(): void
    {
        $response = $this->actingAs($this->teller)
            ->get(route('teller.laporan.excel', $this->report));

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'laporan-harian-') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);
        $titles = $spreadsheet->getSheetNames();

        $rekap = $spreadsheet->getSheetByName('Rekap');
        $transaksi = $spreadsheet->getSheetByName('Transaksi');

        $this->assertSame('LAPORAN HARIAN TELLER (PENUTUPAN KAS)', $rekap->getCell('A1')->getValue());
        $this->assertSame('Teller Laporan', $rekap->getCell('B3')->getValue());
        // Baris 8 = saldo awal (judul, tanggal, teller, status, dicetak, kosong, header).
        $this->assertSame(150000.0, (float) $rekap->getCell('B8')->getValue());
        // Baris terakhir berisi saldo akhir sistem.
        $this->assertSame(245000.0, (float) $rekap->getCell('B13')->getValue());
        $this->assertSame('REFLAPORAN001', $transaksi->getCell('B2')->getValue());

        $spreadsheet->disconnectWorksheets();
        @unlink($path);

        $this->assertSame(['Rekap', 'Rincian Lembaran', 'Transaksi'], $titles);
    }

    public function test_reconciliation_exports_as_pdf_and_excel(): void
    {
        $this->actingAs($this->supervisor)->get(route('supervisor.rekonsiliasi'))->assertOk();

        $this->assertPdf(
            $this->actingAs($this->supervisor)->get(route('supervisor.rekonsiliasi.pdf')),
            'REKONSILIASI KAS BANK MINI'
        );

        $this->assertExcel(
            $this->actingAs($this->supervisor)->get(route('supervisor.rekonsiliasi.excel'))
        );
    }

    public function test_attendance_report_exports_as_pdf_and_excel(): void
    {
        $this->assertPdf(
            $this->actingAs($this->supervisor)->get(route('supervisor.absensi.pdf')),
            'LAPORAN ABSENSI PETUGAS'
        );

        $this->assertExcel(
            $this->actingAs($this->supervisor)->get(route('supervisor.absensi.export'))
        );
    }

    public function test_admin_audit_log_exports_as_pdf_and_excel(): void
    {
        $this->assertPdf(
            $this->actingAs($this->admin)->get(route('admin.audit-log.pdf')),
            'LOG & AUDIT SISTEM'
        );

        $this->assertExcel(
            $this->actingAs($this->admin)->get(route('admin.audit-log.excel'))
        );
    }

    public function test_slip_print_is_pdf_only_for_teller_and_customer(): void
    {
        $this->assertPdf(
            $this->actingAs($this->teller)->get(route('teller.transaksi.cetak-pdf', $this->deposit)),
            'SLIP SETORAN TUNAI'
        );

        $this->assertPdf(
            $this->actingAs($this->customer)->get(route('customer.receipt.pdf', $this->deposit)),
            'SLIP SETORAN TUNAI'
        );

        // Halaman pratinjau slip tidak lagi menyediakan cetak HTML (window.print).
        $this->actingAs($this->teller)
            ->get(route('teller.transaksi.cetak', $this->deposit))
            ->assertOk()
            ->assertSee('Unduh PDF')
            ->assertDontSee('window.print()');

        $this->actingAs($this->customer)
            ->get(route('customer.receipt', $this->deposit))
            ->assertOk()
            ->assertSee('Unduh PDF')
            ->assertDontSee('window.print()');
    }

    public function test_long_audit_journal_pdf_is_paginated_and_valid(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->makeTransaction('deposit', 1000, 'REFMULTI' . $i);
        }

        $response = $this->actingAs($this->supervisor)->get(route('supervisor.jurnal.pdf'));
        $response->assertOk();

        $bytes = $response->getContent();
        $this->assertSame(1, preg_match('/\/Count ([2-9]|\d{2,})/', $bytes));
        $this->assertStringEndsWith('%%EOF', $bytes);

        $xrefPosition = (int) trim(substr($bytes, strrpos($bytes, 'startxref') + 9));
        $this->assertSame('xref', substr($bytes, $xrefPosition, 4));
    }
}
