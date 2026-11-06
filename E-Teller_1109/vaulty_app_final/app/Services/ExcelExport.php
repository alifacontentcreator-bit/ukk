<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penulis berkas Excel untuk unduhan: templat impor dan ekspor laporan.
 */
class ExcelExport
{
    /** Hindari rumus tersembunyi (=, +, -, @) di sel Excel dari teks yang diketik pengguna. */
    private function safe(mixed $value): mixed
    {
        return (is_string($value) && preg_match('/^[=+\-@]/', $value)) ? "'" . $value : $value;
    }

    public function download(string $filename, array $headers, iterable $rows, string $title = 'Data'): StreamedResponse
    {
        $data = [$headers];
        foreach ($rows as $row) {
            $data[] = array_map(fn ($v) => $this->safe($v), array_values($row));
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->fromArray($data, null, 'A1');

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('55693D');
        $sheet->freezePane('A2');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
