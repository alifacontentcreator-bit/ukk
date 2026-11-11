<?php
/* Tujuan file: Penulis berkas PDF sederhana (tanpa dependensi tambahan) yang dipakai
   service laporan untuk menggabungkan banyak halaman menjadi satu dokumen valid. */

namespace App\Support;

class SimplePdfWriter
{
    public function __construct(
        private float $pageWidth,
        private float $pageHeight
    ) {
    }

    /**
     * @param array<int, string> $contents isi stream setiap halaman (koordinat PDF, satuan poin)
     */
    public function build(array $contents): string
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
                . $this->number($this->pageWidth) . ' ' . $this->number($this->pageHeight)
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

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
