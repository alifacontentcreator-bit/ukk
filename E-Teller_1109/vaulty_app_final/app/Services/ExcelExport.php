<?php
/* Tujuan file: Service ini menampung proses bisnis bersama untuk fitur excel export agar dapat dipakai konsisten oleh beberapa bagian aplikasi. */


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

    public function download(
        string $filename,
        array $headers,
        iterable $rows,
        string $title = 'Data',
        array $meta = []
    ): StreamedResponse {
        $data = [];

        // Baris informasi di atas tabel (mis. identitas rekening & periode mutasi).
        foreach ($meta as $line) {
            $line = is_array($line) ? $line : [$line];
            $data[] = array_map(fn ($v) => $this->safe($v), array_values($line));
        }

        $headerRow = count($data) + 1;
        $data[] = $headers;

        foreach ($rows as $row) {
            $data[] = array_map(fn ($v) => $this->safe($v), array_values($row));
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->fromArray($data, null, 'A1');

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
            ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('55693D');

        if (!empty($meta)) {
            $sheet->getStyle('A1')->getFont()->setBold(true);
        }

        $sheet->freezePane('A' . ($headerRow + 1));
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
