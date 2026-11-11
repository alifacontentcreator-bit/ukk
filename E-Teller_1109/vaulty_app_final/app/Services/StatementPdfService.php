<?php
/* Tujuan file: Service ini membuat berkas PDF mutasi rekening (A4 landscape, multi-halaman) tanpa
   dependensi tambahan, mengikuti gaya dokumen resmi BANK MINI. */

namespace App\Services;

/**
 * Format kolom mengikuti permintaan cetak mutasi:
 * Tanggal Transaksi | Keterangan/Deskripsi | Referensi | Mutasi Debit (DB) |
 * Mutasi Kredit (CR) | Saldo Akhir.
 */
class StatementPdfService
{
    private const PAGE_WIDTH = 842;   // A4 landscape (points)
    private const PAGE_HEIGHT = 595;

    // Kolom tabel (x kiri untuk teks, x kanan untuk angka).
    private const COL_NO = 32;
    private const COL_DATE = 58;
    private const COL_DESC = 168;
    private const COL_REF = 398;
    private const COL_DEBIT_RIGHT = 600;
    private const COL_CREDIT_RIGHT = 702;
    private const COL_BALANCE_RIGHT = 810;

    private const ROW_HEIGHT = 16;
    private const ROWS_FIRST_PAGE = 20;
    private const ROWS_PER_PAGE = 25;

    public function render(array $statement, ?string $requestCode = null): string
    {
        $rows = $statement['rows'] ?? [];
        $total = count($rows);

        // Bagi baris ke halaman; halaman pertama lebih pendek karena blok identitas.
        $chunks = [];
        $index = 0;
        do {
            $capacity = empty($chunks) ? self::ROWS_FIRST_PAGE : self::ROWS_PER_PAGE;
            $chunk = array_slice($rows, $index, $capacity);
            $chunks[] = $chunk;
            $index += count($chunk);
        } while ($index < $total);

        $contents = [];
        $startNo = 1;
        foreach ($chunks as $pageIndex => $chunk) {
            $contents[] = $this->page(
                $statement,
                $chunk,
                $pageIndex + 1,
                count($chunks),
                $startNo,
                $requestCode
            );
            $startNo += count($chunk);
        }

        return $this->buildPdf($contents);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function page(
        array $statement,
        array $rows,
        int $page,
        int $pageCount,
        int $startNo,
        ?string $requestCode
    ): string {
        $content = '';

        // Watermark BANK MINI (samar, di belakang tabel).
        $content .= "q 0.955 g BT /F2 92 Tf 250 250 Td (BANK MINI) Tj ET Q\n";

        $content .= $this->text(self::COL_NO, 566, 12, 'LKM MITRA SISWA ABADI', true);
        $content .= $this->text(self::COL_NO, 552, 8, 'BANK MINI - SMK NEGERI 1 BANTUL');
        $content .= $this->text(0, 566, 8, 'MUTASI REKENING / STATEMENT OF ACCOUNT', true, self::COL_BALANCE_RIGHT);
        $content .= $this->text(0, 552, 7, 'Dokumen resmi - diterbitkan oleh sistem', false, self::COL_BALANCE_RIGHT);
        $content .= $this->rule(self::COL_NO, 543, self::COL_BALANCE_RIGHT);

        if ($page === 1) {
            $content .= $this->text(self::COL_NO, 522, 10, 'REKENING TABUNGAN - MUTASI PERIODE '
                . $statement['period_from'] . ' s/d ' . $statement['period_to'], true);

            $content .= $this->text(self::COL_NO, 505, 8, 'No. Rekening: ' . $statement['account_number']);
            $content .= $this->text(430, 505, 8, 'Nama Nasabah: ' . $statement['customer_name']);
            $content .= $this->text(self::COL_NO, 491, 8, 'Jenis Tabungan: ' . $statement['product_type']);
            $content .= $this->text(430, 491, 8, 'Kelas: ' . ($statement['customer_class'] ?: '-'));
            $content .= $this->text(self::COL_NO, 477, 8, 'Dicetak: ' . now()->format('d/m/Y H:i') . ' WIB');
            $content .= $this->text(430, 477, 8, 'Kode Permintaan: ' . ($requestCode ?: '-'));
            $content .= $this->text(430, 463, 8, 'Jumlah Transaksi: ' . $statement['total_transactions'] . ' baris');

            $content .= $this->summaryBoxes($statement);
            $tableTop = 414;
        } else {
            $content .= $this->text(self::COL_NO, 522, 9, 'MUTASI REKENING ' . $statement['account_number']
                . ' - PERIODE ' . $statement['period_from'] . ' s/d ' . $statement['period_to'], true);
            $tableTop = 500;
        }

        $content .= $this->tableHeader($tableTop);

        $y = $tableTop - 22;
        if (empty($rows)) {
            $content .= $this->text(self::COL_NO, $y, 8, 'Tidak ada mutasi pada periode ini.');
        }

        $number = $startNo;
        foreach ($rows as $row) {
            $content .= $this->text(self::COL_NO, $y, 7.5, (string) $number);
            $content .= $this->text(self::COL_DATE, $y, 7.5, $row['date'] . ' ' . $row['time']);
            $content .= $this->text(self::COL_DESC, $y, 7.5, $this->clip((string) $row['description'], 44));
            $content .= $this->text(self::COL_REF, $y, 6.5, $this->clip((string) $row['reference'], 22));

            $debit = (float) $row['debit'];
            $credit = (float) $row['credit'];

            $content .= $this->text(0, $y, 7.5, $debit > 0 ? $this->money($debit) : '', false, self::COL_DEBIT_RIGHT);
            $content .= $this->text(0, $y, 7.5, $credit > 0 ? $this->money($credit) : '', false, self::COL_CREDIT_RIGHT);
            $content .= $this->text(0, $y, 7.5, $this->money((float) $row['balance']), false, self::COL_BALANCE_RIGHT);

            $y -= self::ROW_HEIGHT;
            $number++;
        }

        $content .= $this->rule(self::COL_NO, 62, self::COL_BALANCE_RIGHT, 0.5, '0.6 0.6 0.6');
        $content .= $this->text(
            self::COL_NO,
            48,
            6.5,
            'Dokumen diterbitkan otomatis oleh sistem BANK MINI. Saldo akhir adalah saldo setelah transaksi tercatat.'
        );
        $content .= $this->text(0, 48, 6.5, 'Halaman ' . $page . ' dari ' . $pageCount, false, self::COL_BALANCE_RIGHT);

        return $content;
    }

    /** Empat kotak ringkasan: saldo awal, total kredit, total debit, saldo akhir. */
    private function summaryBoxes(array $statement): string
    {
        $cells = [
            ['Saldo Awal', $statement['opening_balance']],
            ['Total Mutasi Kredit (CR)', $statement['total_credit']],
            ['Total Mutasi Debit (DB)', $statement['total_debit']],
            ['Saldo Akhir', $statement['closing_balance']],
        ];

        $width = 190.0;
        $gap = 6.0;
        $height = 34.0;
        $y = 424.0;
        $content = '';

        foreach ($cells as $index => [$label, $value]) {
            $x = self::COL_NO + ($index * ($width + $gap));

            $content .= sprintf("q 0.955 0.96 0.945 rg %.2f %.2f %.2f %.2f re f Q\n", $x, $y, $width, $height);
            $content .= sprintf("0.78 0.80 0.76 RG 0.5 w %.2f %.2f %.2f %.2f re S\n", $x, $y, $width, $height);
            $content .= $this->text($x + 6, $y + 22, 6.5, strtoupper($label));
            $content .= $this->text($x + 6, $y + 9, 9, 'Rp ' . $this->money((float) $value), true);
        }

        return $content;
    }

    private function tableHeader(float $y): string
    {
        $content = sprintf("q 0.90 0.92 0.88 rg %.2f %.2f %.2f 16 re f Q\n", self::COL_NO, $y - 11, 778.0);

        $content .= $this->text(self::COL_NO, $y, 7.5, 'No', true);
        $content .= $this->text(self::COL_DATE, $y, 7.5, 'Tanggal Transaksi', true);
        $content .= $this->text(self::COL_DESC, $y, 7.5, 'Keterangan / Deskripsi Transaksi', true);
        $content .= $this->text(self::COL_REF, $y, 7.5, 'Referensi', true);
        $content .= $this->text(0, $y, 7.5, 'Mutasi Debit (DB)', true, self::COL_DEBIT_RIGHT);
        $content .= $this->text(0, $y, 7.5, 'Mutasi Kredit (CR)', true, self::COL_CREDIT_RIGHT);
        $content .= $this->text(0, $y, 7.5, 'Saldo Akhir', true, self::COL_BALANCE_RIGHT);

        return $content . $this->rule(self::COL_NO, $y - 11, self::COL_BALANCE_RIGHT);
    }

    private function text(
        float $x,
        float $y,
        float $size,
        string $value,
        bool $bold = false,
        ?float $rightX = null
    ): string {
        $font = $bold ? 'F2' : 'F1';

        if ($rightX !== null) {
            $x = $rightX - $this->estimateWidth($value, $size);
        }

        return sprintf(
            "BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $font,
            $size,
            $x,
            $y,
            $this->escape($value)
        );
    }

    private function rule(
        float $x1,
        float $y,
        float $x2,
        float $width = 0.7,
        string $color = '0.35 0.38 0.33'
    ): string {
        return sprintf(
            "%s RG %.2f w %.2f %.2f m %.2f %.2f l S\n",
            $color,
            $width,
            $x1,
            $y,
            $x2,
            $y
        );
    }

    /** Format angka: 10.000,00 */
    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function clip(string $value, int $maxChars): string
    {
        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxChars - 3)) . '...';
    }

    /** Perkiraan lebar teks Helvetica untuk perataan kanan. */
    private function estimateWidth(string $value, float $size): float
    {
        $width = 0.0;

        foreach (mb_str_split($value) as $char) {
            $width += match (true) {
                in_array($char, ['.', ',', ' ', ':', '/', '-', "'", '(', ')'], true) => 0.278,
                ctype_digit($char) => 0.556,
                ctype_upper($char) => 0.667,
                default => 0.50,
            };
        }

        return $width * $size;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        $value = str_replace('\\', '\\\\', $value);

        return str_replace(['(', ')'], ['\\(', '\\)'], $value);
    }

    /**
     * Menyusun berkas PDF multi-halaman.
     *
     * @param array<int, string> $contents isi stream setiap halaman
     */
    private function buildPdf(array $contents): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $kids = [];
        $next = 5;

        foreach ($contents as $content) {
            $pageId = $next++;
            $contentId = $next++;

            $kids[] = $pageId . ' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '
                . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n"
                . $content . "endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count '
            . count($contents) . ' >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $maxId = max(array_keys($objects));
        $xrefPosition = strlen($pdf);

        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n"
            . $xrefPosition . "\n%%EOF";

        return $pdf;
    }
}
