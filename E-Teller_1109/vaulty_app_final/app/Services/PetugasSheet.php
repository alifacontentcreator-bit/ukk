<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Baca/tulis file Excel petugas jaga. Kolom mengikuti berkas absensi yang dipakai
 * sekolah: Nama, Kelas, No. WA yang bisa dihubungi.
 */
class PetugasSheet
{
    private const MAX_ROWS = 500;

    // ---------- Normalisasi ----------

    public function normalizeName(string $value): string
    {
        return (string) Str::of($value)->lower()->replaceMatches('/[^a-z\s]/', '')->squish();
    }

    /** "XII RPL 1" dan "12 rpl 1" dianggap sama. */
    public function normalizeClass(string $value): string
    {
        $v = Str::lower($value);
        $v = preg_replace(['/\bxii\b/', '/\bxi\b/', '/\bx\b/'], ['12', '11', '10'], $v);

        return preg_replace('/[^a-z0-9]/', '', $v);
    }

    /** Hasil: digit saja dengan awalan 0 (mis. 081234567890), atau null bila tidak valid. */
    public function normalizePhone(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value);

        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        return (str_starts_with($digits, '0') && strlen($digits) >= 9 && strlen($digits) <= 15) ? $digits : null;
    }

    /** Tautan WhatsApp dari nomor lokal. */
    public function waLink(?string $phone): ?string
    {
        $normalized = $phone ? $this->normalizePhone($phone) : null;

        return $normalized ? 'https://wa.me/62' . substr($normalized, 1) : null;
    }

    // ---------- Impor ----------

    /**
     * Perbarui No. WA akun teller dari file Excel/CSV (kolom Nama, Kelas, No. WA).
     * Pencocokan lewat nama (dan kelas bila ada); baris yang tidak pasti DILEWATI, bukan ditebak.
     *
     * @return array{updated:int, unchanged:int, skipped:array<int,array{row:int,nama:string,alasan:string}>}
     */
    public function import(UploadedFile $file): array
    {
        try {
            $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            throw new DomainException('File tidak dapat dibaca. Gunakan file Excel (.xlsx/.xls) atau CSV.');
        }

        [$headerIndex, $colName, $colClass, $colPhone] = $this->findColumns($rows);

        $tellers = User::where('role', 'teller')->get();
        $byName = [];
        foreach ($tellers as $t) {
            $byName[$this->normalizeName((string) $t->name)][] = $t;
        }

        $result = ['updated' => 0, 'unchanged' => 0, 'skipped' => []];
        $processed = 0;

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            $name = trim((string) ($row[$colName] ?? ''));
            if ($name === '') {
                continue;
            }
            if (++$processed > self::MAX_ROWS) {
                $result['skipped'][] = ['row' => $i + 1, 'nama' => $name, 'alasan' => 'Melebihi batas ' . self::MAX_ROWS . ' baris per impor.'];
                break;
            }

            $skip = fn (string $reason) => $result['skipped'][] = ['row' => $i + 1, 'nama' => $name, 'alasan' => $reason];

            $phone = $this->normalizePhone((string) ($row[$colPhone] ?? ''));
            if (!$phone) {
                $skip('Nomor WA kosong atau tidak valid.');
                continue;
            }

            $candidates = $byName[$this->normalizeName($name)] ?? [];
            $class = $colClass !== null ? trim((string) ($row[$colClass] ?? '')) : '';

            if ($class !== '' && count($candidates) > 1) {
                $candidates = array_values(array_filter($candidates, fn ($t) => $this->normalizeClass((string) $t->kelas) === $this->normalizeClass($class)));
            }

            if (count($candidates) === 0) {
                $skip('Nama tidak ditemukan di akun teller.');
                continue;
            }
            if (count($candidates) > 1) {
                $skip('Ada lebih dari satu teller dengan nama ini dan kelas tidak membedakan.');
                continue;
            }

            $teller = $candidates[0];

            if ($class !== '' && $teller->kelas && $this->normalizeClass((string) $teller->kelas) !== $this->normalizeClass($class)) {
                $skip('Kelas tidak cocok (di sistem: ' . $teller->kelas . ').');
                continue;
            }

            if ($teller->no_telp === $phone) {
                $result['unchanged']++;
                continue;
            }

            $teller->update(['no_telp' => $phone]);
            $result['updated']++;
        }

        return $result;
    }

    /** @return array{0:int,1:int,2:?int,3:int} indeks baris header, kolom nama, kelas, WA */
    private function findColumns(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $name = $class = $phone = null;

            foreach ($row as $col => $cell) {
                $h = preg_replace('/[^a-z0-9]/', '', Str::lower((string) $cell));
                if ($h === '') {
                    continue;
                }
                if ($name === null && str_starts_with($h, 'nama')) $name = $col;
                elseif ($class === null && str_starts_with($h, 'kelas')) $class = $col;
                elseif ($phone === null && (str_contains($h, 'wa') || str_contains($h, 'telp') || str_contains($h, 'hp'))) $phone = $col;
            }

            if ($name !== null && $phone !== null) {
                return [$i, $name, $class, $phone];
            }
        }

        throw new DomainException('Kolom tidak ditemukan. Baris judul harus memuat: Nama, Kelas, No. WA.');
    }

    // ---------- Ekspor ----------

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