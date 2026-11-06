<?php

namespace App\Services;

/**
 * Membuat PDF slip transaksi sederhana tanpa dependensi tambahan.
 * Format mengikuti halaman cetak teller: no. referensi, no. rekening, nama,
 * tanggal, jam, jenis transaksi, jumlah tunai, nama teller, dan watermark
 * BANK MINI.
 */
class SlipPdfService
{
    private const PAGE_WIDTH = 360;
    private const PAGE_HEIGHT = 560;

    public function render(array $slip): string
    {
        $rows = [
            ['No. Referensi', $slip['reference_number'] ?? '-'],
            ['No. Rekening', $slip['account_number'] ?? '-'],
            ['Nama', $slip['customer_name'] ?? '-'],
            ['Tanggal', $slip['date'] ?? '-'],
            ['Jam', $slip['time'] ?? '-'],
            ['Jenis Transaksi', ($slip['type_label'] ?? '-') . ' Tunai'],
            ['Jumlah Tunai', $slip['amount_formatted'] ?? '-'],
            ['Nama Teller', $slip['teller_name'] ?? '-'],
        ];

        $content = '';

        // Watermark BANK MINI (digambar lebih dulu, di belakang teks).
        $content .= "q 0.90 0.90 0.90 rg BT /F2 42 Tf 24 300 Td (BANK MINI) Tj ET Q\n";

        $content .= "0 0 0 rg\n";
        $content .= "BT /F2 13 Tf 20 528 Td (LKM MITRA SISWA ABADI) Tj ET\n";
        $content .= "BT /F1 9 Tf 20 511 Td (BANK MINI - SMK NEGERI 1 BANTUL) Tj ET\n";
        $content .= "0.2 0.2 0.2 RG 0.8 w 20 502 m 340 502 l S\n";
        $content .= 'BT /F2 11 Tf 20 482 Td (SLIP '
            . $this->escape(strtoupper($slip['type_label'] ?? 'TRANSAKSI'))
            . ' TUNAI) Tj ET' . "\n";

        $y = 456;
        foreach ($rows as [$label, $value]) {
            $content .= sprintf(
                "BT /F1 9 Tf 20 %d Td (%s) Tj ET\n",
                $y,
                $this->escape((string) $label)
            );
            $content .= sprintf(
                "BT /F2 9 Tf 130 %d Td (%s) Tj ET\n",
                $y,
                $this->escape((string) $value)
            );
            $y -= 18;
        }

        $y -= 4;
        $content .= "0.2 0.2 0.2 RG 0.6 w 20 {$y} m 340 {$y} l S\n";
        $y -= 16;

        foreach ($this->wrap('Terbilang: ' . ($slip['amount_in_words'] ?? '-')) as $line) {
            $content .= sprintf(
                "BT /F1 8 Tf 20 %d Td (%s) Tj ET\n",
                $y,
                $this->escape($line)
            );
            $y -= 11;
        }

        $y -= 6;
        $content .= sprintf(
            "BT /F1 8 Tf 20 %d Td (%s) Tj ET\n",
            $y,
            $this->escape('Kode verifikasi: ' . ($slip['verification_code'] ?? '-'))
        );

        $content .= "BT /F1 7 Tf 20 60 Td (Dokumen diterbitkan otomatis oleh sistem BANK MINI.) Tj ET\n";
        $content .= "BT /F1 7 Tf 20 48 Td (Slip resmi tanpa tanda tangan; watermark BANK MINI sebagai penanda.) Tj ET\n";

        return $this->buildPdf($content);
    }

    private function buildPdf(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' '
                . self::PAGE_HEIGHT
                . '] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[$index] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1)
            . " /Root 1 0 R >>\nstartxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $maxChars = 68): array
    {
        return explode("\n", wordwrap($text, $maxChars, "\n", true));
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        $value = str_replace('\\', '\\\\', $value);

        return str_replace(['(', ')'], ['\\(', '\\)'], $value);
    }
}
