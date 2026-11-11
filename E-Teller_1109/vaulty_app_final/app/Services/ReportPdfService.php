<?php
/* Tujuan file: Service PDF generik untuk laporan (A4 portrait/landscape, multi-halaman).
   Dokumen disusun dari judul, meta, beberapa tabel (section), dan catatan kaki. */

namespace App\Services;

use App\Support\SimplePdfWriter;

class ReportPdfService
{
    private const MARGIN = 32.0;
    private const BOTTOM_LIMIT = 64.0;
    private const ROW_HEIGHT = 15.0;

    private float $width = 595;
    private float $height = 842;
    private float $contentWidth = 531;
    private float $y = 0;
    private string $buffer = '';
    /** @var array<int, string> */
    private array $pages = [];
    private bool $firstPage = true;
    /** @var array<string, mixed> */
    private array $document = [];

    /**
     * Struktur $document:
     * - title      : string
     * - subtitle   : ?string
     * - meta       : array<int, array{0:string,1:string}>
     * - sections   : array<int, array{heading?:string, headers:array<int,string>,
     *                aligns?:array<int,string>, widths?:array<int,float>, rows:array<int,array<int,mixed>>,
     *                totals?:array<int,array<int,mixed>>}>
     * - notes      : array<int, string>
     * - printed_by : ?string
     * - orientation: 'portrait'|'landscape'
     */
    public function render(array $document): string
    {
        $landscape = ($document['orientation'] ?? 'portrait') === 'landscape';

        $this->width = $landscape ? 842 : 595;
        $this->height = $landscape ? 595 : 842;
        $this->contentWidth = $this->width - (2 * self::MARGIN);
        $this->document = $document;
        $this->buffer = '';
        $this->pages = [];
        $this->firstPage = true;

        $this->startPage();

        foreach ($document['sections'] ?? [] as $section) {
            $this->drawSection(is_array($section) ? $section : []);
        }

        $this->drawNotes($document['notes'] ?? []);
        $this->flushPage();

        $total = count($this->pages);
        foreach ($this->pages as $index => $content) {
            $this->pages[$index] = $content . $this->footer($index + 1, $total);
        }

        return (new SimplePdfWriter($this->width, $this->height))->build($this->pages);
    }

    private function startPage(): void
    {
        $this->buffer = '';
        $this->y = $this->height - self::MARGIN;

        if ($this->firstPage) {
            $this->firstPage = false;
            $this->drawFirstPageHeader();
        } else {
            $this->drawContinuationHeader();
        }
    }

    private function newPage(): void
    {
        $this->flushPage();
        $this->startPage();
    }

    private function flushPage(): void
    {
        if (trim($this->buffer) !== '') {
            $this->pages[] = $this->buffer;
        }

        $this->buffer = '';
    }

    private function drawFirstPageHeader(): void
    {
        $center = $this->width / 2;

        $this->buffer .= $this->textString($center, $this->y - 12, 11, 'LKM MITRA SISWA ABADI', true, '0.18 0.25 0.15', true);
        $this->y -= 24;
        $this->buffer .= $this->textString($center, $this->y - 2, 8, 'BANK MINI - SMK NEGERI 1 BANTUL', false, '0.35 0.38 0.33', true);
        $this->y -= 20;

        $this->buffer .= $this->textString($center, $this->y - 10, 13, strtoupper((string) ($this->document['title'] ?? 'LAPORAN')), true, '0.12 0.16 0.11', true);
        $this->y -= 22;

        $subtitle = trim((string) ($this->document['subtitle'] ?? ''));
        if ($subtitle !== '') {
            $this->buffer .= $this->textString($center, $this->y - 2, 8, $subtitle, false, '0.35 0.38 0.33', true);
            $this->y -= 14;
        }

        $this->buffer .= $this->line(self::MARGIN, $this->y, $this->width - self::MARGIN, 0.9);
        $this->y -= 14;

        $meta = array_values($this->document['meta'] ?? []);
        if ($meta !== []) {
            $columnWidth = $this->contentWidth / 2;
            $labelWidth = 78;

            foreach (array_chunk($meta, 2) as $pairs) {
                $lineY = $this->y - 9;

                foreach (array_values($pairs) as $column => $pair) {
                    $label = trim((string) ($pair[0] ?? ''));
                    $value = trim((string) ($pair[1] ?? ''));

                    if ($label === '' && $value === '') {
                        continue;
                    }

                    $x = self::MARGIN + ($column * $columnWidth);
                    $maxChars = max(6, (int) (($columnWidth - $labelWidth - 8) / 4.2));

                    $this->buffer .= $this->textString($x, $lineY, 7.5, $label . ':', false, '0.40 0.44 0.38');
                    $this->buffer .= $this->textString(
                        $x + $labelWidth,
                        $lineY,
                        8,
                        $this->clip($value, $maxChars),
                        true,
                        '0.12 0.16 0.11'
                    );
                }

                $this->y -= 13;
            }
        }

        $this->y -= 3;
        $this->buffer .= $this->line(self::MARGIN, $this->y, $this->width - self::MARGIN, 0.6);
        $this->y -= 10;
    }

    private function drawContinuationHeader(): void
    {
        $this->buffer .= $this->textString(self::MARGIN, $this->y - 10, 9, 'LKM MITRA SISWA ABADI', true);
        $this->buffer .= $this->textString(
            $this->width - self::MARGIN,
            $this->y - 10,
            8,
            strtoupper((string) ($this->document['title'] ?? 'LAPORAN')),
            true,
            '0.30 0.34 0.28',
            false,
            true
        );
        $this->y -= 18;
        $this->buffer .= $this->line(self::MARGIN, $this->y, $this->width - self::MARGIN, 0.6);
        $this->y -= 12;
    }

    /** @param array<string, mixed> $section */
    private function drawSection(array $section): void
    {
        $headers = array_values($section['headers'] ?? []);
        $rows = array_values($section['rows'] ?? []);
        $aligns = array_values($section['aligns'] ?? []);
        $widths = $this->columnWidths(array_values($section['widths'] ?? []), count($headers));
        $heading = trim((string) ($section['heading'] ?? ''));

        if ($widths === []) {
            return;
        }

        $this->ensureSpace(40);

        if ($heading !== '') {
            $this->buffer .= $this->textString(self::MARGIN, $this->y - 10, 9, $heading, true, '0.18 0.25 0.15');
            $this->y -= 17;
        }

        $this->drawTableHeader($headers, $widths, $aligns);

        if ($rows === []) {
            $this->buffer .= $this->textString(self::MARGIN + 4, $this->y - 11, 7.5, 'Tidak ada data pada filter ini.', false, '0.40 0.44 0.38');
            $this->y -= self::ROW_HEIGHT;
        }

        foreach ($rows as $row) {
            if ($this->y - self::ROW_HEIGHT < self::BOTTOM_LIMIT) {
                $this->newPage();
                if ($heading !== '') {
                    $this->buffer .= $this->textString(self::MARGIN, $this->y - 10, 9, 'Lanjutan - ' . $heading, true, '0.18 0.25 0.15');
                    $this->y -= 17;
                }
                $this->drawTableHeader($headers, $widths, $aligns);
            }

            $this->drawRow(is_array($row) ? $row : [], $widths, $aligns, false);
        }

        foreach ($section['totals'] ?? [] as $row) {
            if ($this->y - self::ROW_HEIGHT < self::BOTTOM_LIMIT) {
                $this->newPage();
                $this->drawTableHeader($headers, $widths, $aligns);
            }

            $this->drawRow(is_array($row) ? $row : [], $widths, $aligns, true);
        }

        $this->y -= 8;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, float> $widths
     * @param array<int, string> $aligns
     */
    private function drawTableHeader(array $headers, array $widths, array $aligns): void
    {
        $this->buffer .= sprintf(
            "q 0.90 0.92 0.88 rg %.2f %.2f %.2f 17 re f Q\n",
            self::MARGIN,
            $this->y - 12,
            $this->contentWidth
        );

        $x = self::MARGIN;

        foreach ($headers as $index => $header) {
            $width = $widths[$index] ?? 0;
            $align = $aligns[$index] ?? 'left';
            $maxChars = max(4, (int) (($width - 8) / 3.7));
            $label = $this->clip((string) $header, $maxChars);

            if ($align === 'right') {
                $this->buffer .= $this->textString($x + $width - 4, $this->y - 8, 7.5, $label, true, '0.18 0.25 0.15', false, true);
            } elseif ($align === 'center') {
                $this->buffer .= $this->textString($x + ($width / 2), $this->y - 8, 7.5, $label, true, '0.18 0.25 0.15', true);
            } else {
                $this->buffer .= $this->textString($x + 4, $this->y - 8, 7.5, $label, true, '0.18 0.25 0.15');
            }

            $x += $width;
        }

        $this->y -= 18;
    }

    /**
     * @param array<int, mixed> $row
     * @param array<int, float> $widths
     * @param array<int, string> $aligns
     */
    private function drawRow(array $row, array $widths, array $aligns, bool $bold): void
    {
        if ($bold) {
            $this->buffer .= sprintf(
                "q 0.965 0.97 0.955 rg %.2f %.2f %.2f %.2f re f Q\n",
                self::MARGIN,
                $this->y - 12,
                $this->contentWidth,
                self::ROW_HEIGHT
            );
        }

        $x = self::MARGIN;

        foreach ($widths as $index => $width) {
            $align = $aligns[$index] ?? 'left';
            $value = trim((string) ($row[$index] ?? ''));
            $maxChars = max(4, (int) (($width - 8) / 3.7));
            $value = $this->clip($value, $maxChars);

            if ($value !== '') {
                if ($align === 'right') {
                    $this->buffer .= $this->textString($x + $width - 4, $this->y - 10, 7.5, $value, $bold, '0.12 0.16 0.11', false, true);
                } elseif ($align === 'center') {
                    $this->buffer .= $this->textString($x + ($width / 2), $this->y - 10, 7.5, $value, $bold, '0.12 0.16 0.11', true);
                } else {
                    $this->buffer .= $this->textString($x + 4, $this->y - 10, 7.5, $value, $bold, '0.12 0.16 0.11');
                }
            }

            $x += $width;
        }

        $this->y -= self::ROW_HEIGHT;
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < self::BOTTOM_LIMIT) {
            $this->newPage();
        }
    }

    /** @param array<int, string> $notes */
    private function drawNotes(array $notes): void
    {
        $notes = array_values(array_filter(array_map('trim', $notes), fn ($note) => $note !== ''));

        if ($notes === []) {
            return;
        }

        $this->ensureSpace(24);
        $this->buffer .= $this->line(self::MARGIN, $this->y, $this->width - self::MARGIN, 0.6);
        $this->y -= 14;

        $maxChars = max(40, (int) ($this->contentWidth / 3.75));

        foreach ($notes as $note) {
            foreach ($this->wrap($note, $maxChars) as $line) {
                if ($this->y - 11 < self::BOTTOM_LIMIT) {
                    $this->newPage();
                }

                $this->buffer .= $this->textString(self::MARGIN, $this->y - 8, 7.5, $line, false, '0.30 0.34 0.28');
                $this->y -= 11;
            }
        }
    }

    private function footer(int $page, int $total): string
    {
        $content = $this->line(self::MARGIN, 58, $this->width - self::MARGIN, 0.5, '0.6 0.6 0.6');
        $content .= $this->textString(
            self::MARGIN,
            45,
            6.5,
            'Dokumen diterbitkan otomatis oleh sistem BANK MINI. Gunakan filter yang sama untuk mencetak ulang.',
            false,
            '0.40 0.44 0.38'
        );

        $printedBy = trim((string) ($this->document['printed_by'] ?? ''));
        if ($printedBy !== '') {
            $content .= $this->textString($this->width - self::MARGIN, 45, 6.5, 'Dicetak oleh: ' . $printedBy, false, '0.40 0.44 0.38', false, true);
        }

        $content .= $this->textString($this->width / 2, 32, 6.5, 'Halaman ' . $page . ' dari ' . $total, false, '0.40 0.44 0.38', true);

        return $content;
    }

    /**
     * Lebar kolom mengikuti bobot yang diberikan; tanpa bobot dibagi rata.
     *
     * @param array<int, float|int> $custom
     * @return array<int, float>
     */
    private function columnWidths(array $custom, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $custom = array_map('floatval', $custom);

        if (count($custom) === $count && array_sum($custom) > 0) {
            $sum = array_sum($custom);

            return array_map(
                fn (float $weight) => ($weight / $sum) * $this->contentWidth,
                $custom
            );
        }

        return array_fill(0, $count, $this->contentWidth / $count);
    }

    private function textString(
        float $x,
        float $y,
        float $size,
        string $value,
        bool $bold = false,
        string $color = '0.12 0.16 0.11',
        bool $centered = false,
        bool $right = false
    ): string {
        if ($value === '') {
            return '';
        }

        if ($centered) {
            $x -= $this->estimateWidth($value, $size) / 2;
        } elseif ($right) {
            $x -= $this->estimateWidth($value, $size);
        }

        return sprintf(
            "BT %s rg /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $color,
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $y,
            $this->escape($value)
        );
    }

    private function line(
        float $x1,
        float $y,
        float $x2,
        float $width = 0.6,
        string $color = '0.35 0.38 0.33'
    ): string {
        return sprintf("%s RG %.2f w %.2f %.2f m %.2f %.2f l S\n", $color, $width, $x1, $y, $x2, $y);
    }

    private function clip(string $value, int $maxChars): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($maxChars < 4 || mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxChars - 2)) . '..';
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $maxChars): array
    {
        return explode("\n", wordwrap($text, max(20, $maxChars), "\n", true));
    }

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
}
