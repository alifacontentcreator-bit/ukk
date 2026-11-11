<?php
/* Tujuan file: Service pembuat berkas Excel (.xlsx) multi-sheet untuk laporan.
   Setiap sheet berisi baris siap tayang; styling header dibuat konsisten. */

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExcelService
{
    /**
     * @param array<int, array{
     *     title: string,
     *     rows: array<int, array<int, mixed>>,
     *     header_row?: int,
     *     bold_rows?: array<int, int>,
     *     title_rows?: array<int, int>,
     *     money_columns?: array<int, string>
     * }> $sheets
     */
    public function download(string $filename, array $sheets): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach (array_values($sheets) as $index => $sheetData) {
            $title = mb_substr(trim((string) ($sheetData['title'] ?? '')), 0, 31);
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($title !== '' ? $title : 'Sheet' . ($index + 1));

            $rows = [];
            foreach (array_values($sheetData['rows'] ?? []) as $rowIndex => $row) {
                $rows[] = array_map(
                    fn ($value) => $this->safe($value),
                    array_values(is_array($row) ? $row : [$row])
                );
            }

            if ($rows === []) {
                $rows = [['Tidak ada data pada filter ini.']];
            }

            $sheet->fromArray($rows, null, 'A1');

            $columnCount = max(array_map('count', $rows));
            $lastColumn = $this->columnLetter($columnCount);

            $headerRow = (int) ($sheetData['header_row'] ?? 1);
            $headerRow = max(1, min($headerRow, count($rows)));

            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('55693D');

            if ($headerRow > 1) {
                $sheet->getStyle("A1:{$lastColumn}" . ($headerRow - 1))->getFont()->setBold(true);
            }

            $sheet->freezePane('A' . ($headerRow + 1));

            foreach ($sheetData['title_rows'] ?? [] as $rowNumber) {
                $rowNumber = (int) $rowNumber;
                if ($rowNumber >= 1 && $rowNumber <= count($rows)) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFont()->setBold(true)->setSize(12);
                }
            }

            foreach ($sheetData['bold_rows'] ?? [] as $rowNumber) {
                $rowNumber = (int) $rowNumber;
                if ($rowNumber >= 1 && $rowNumber <= count($rows)) {
                    $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFont()->setBold(true);
                }
            }

            foreach ($sheetData['money_columns'] ?? [] as $column) {
                $column = strtoupper((string) $column);
                if ($column !== '' && $columnCount > 0) {
                    $sheet->getStyle("{$column}{$headerRow}:{$column}" . count($rows))
                        ->getNumberFormat()->setFormatCode('#,##0');
                }
            }

            for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
                $letter = $this->columnLetter($columnIndex);
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Hindari rumus tersembunyi dari teks yang diketik pengguna. */
    private function safe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
