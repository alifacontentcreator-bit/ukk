<?php
/* Tujuan file: Service ini menampung proses bisnis bersama untuk fitur account number generator agar dapat dipakai konsisten oleh beberapa bagian aplikasi. */


namespace App\Services;

use App\Models\Account;
use App\Models\SystemSetting;
use App\Models\User;

/**
 * Generator Nomor Rekening & data kelas nasabah.
 *
 * Format nomor rekening siswa: <JURUSAN>-<ANGKATAN>-<KELAS_URUT>-<PRESENSI>
 * Contoh: RPL-2024-01-01
 *
 * - JURUSAN     : kode jurusan (RPL, TKJ, DKV, AK, LPS, MP, BR, BD)
 * - ANGKATAN    : tahun masuk siswa (4 digit), dihitung dari tingkat kelas saat ini
 * - KELAS_URUT  : urutan kelas dalam jurusan yang sama, zero-padded 2 digit (RPL1 -> 01, RPL2 -> 02, dst)
 * - PRESENSI    : nomor urut pendaftaran siswa dalam kombinasi jurusan+angkatan+kelas yang sama,
 *                 zero-padded 2 digit, dihitung OTOMATIS oleh sistem (bukan dipilih manual).
 *
 * Format nomor rekening guru/karyawan: <JABATAN>-<NIP>
 */
class AccountNumberGenerator
{
    /**
     * Daftar kelas resmi per tingkat. Key = tingkat (10/11/12), Value = daftar kode kelas mentah
     * (kode jurusan + nomor urut kelas, tanpa spasi).
     */
    public const CLASS_STRUCTURE = [
        10 => ['RPL1', 'RPL2', 'TKJ1', 'TKJ2', 'DKV1', 'DKV2', 'AK1', 'AK2', 'AK3', 'LPS1', 'LPS2', 'MP1', 'MP2', 'BR1', 'BR2', 'BD'],
        11 => ['RPL1', 'RPL2', 'TKJ1', 'TKJ2', 'DKV1', 'DKV2', 'AK1', 'AK2', 'AK3', 'LPS1', 'LPS2', 'MP1', 'MP2', 'BR1', 'BR2', 'BD'],
        12 => ['RPL1', 'RPL2', 'TKJ1', 'TKJ2', 'DKV1', 'DKV2', 'AK1', 'AK2', 'AK3', 'AK4', 'LPS', 'MP1', 'MP2', 'BR1', 'BR2', 'BD'],
    ];

    public const TINGKAT_LABEL = [10 => 'X', 11 => 'XI', 12 => 'XII'];
    public const TINGKAT_FROM_ROMAN = ['X' => 10, 'XI' => 11, 'XII' => 12];

    /** Setting key untuk tahun masuk (angkatan) siswa kelas X pada tahun ajaran berjalan. */
    public const SETTING_INTAKE_YEAR_X = 'intake_year_kelas_x';

    /**
     * Opsi dropdown kelas, dikelompokkan per tingkat.
     * value & label memakai format label penuh, mis. "XII RPL1" / "XII RPL 1",
     * supaya bisa langsung diparsing lewat resolveClassAttributes().
     */
    public static function classOptions(): array
    {
        $options = [];
        foreach (self::CLASS_STRUCTURE as $tingkat => $classes) {
            $tingkatLabel = self::TINGKAT_LABEL[$tingkat];
            foreach ($classes as $classCode) {
                [$jurusan, $urutNumber] = self::parseClassCode($classCode);
                $options[$tingkat][] = [
                    'value' => $tingkatLabel . ' ' . $classCode, // mis. "XII RPL1"
                    'label' => $tingkatLabel . ' ' . $jurusan . ($urutNumber ? ' ' . $urutNumber : ''),
                ];
            }
        }
        return $options;
    }

    /** Memecah kode kelas mentah, mis. "RPL2" -> ['RPL', 2], "BD" -> ['BD', null] */
    protected static function parseClassCode(string $classCode): array
    {
        if (preg_match('/^([A-Z]+)(\d*)$/', $classCode, $m)) {
            $jurusan = $m[1];
            $urut = $m[2] !== '' ? (int) $m[2] : null;
            return [$jurusan, $urut];
        }
        return [$classCode, null];
    }

    /** Menghitung angkatan (tahun masuk) berdasarkan tingkat kelas saat ini. */
    public static function resolveAngkatan(int $tingkat): int
    {
        $intakeYearX = (int) (SystemSetting::where('key', self::SETTING_INTAKE_YEAR_X)->value('value') ?: date('Y'));
        return $intakeYearX - ($tingkat - 10);
    }

    /**
     * Menyiapkan atribut kelas siswa (jurusan, tingkat, kelas_urut, angkatan, label kelas)
     * dari label lengkap seperti "XII RPL1" atau "X BD".
     */
    public static function resolveClassAttributes(string $fullLabel): array
    {
        $fullLabel = trim($fullLabel);
        [$roman, $classCode] = array_pad(explode(' ', $fullLabel, 2), 2, null);
        $tingkat = self::TINGKAT_FROM_ROMAN[strtoupper((string) $roman)] ?? null;

        if (!$tingkat || !$classCode) {
            throw new \InvalidArgumentException("Format kelas tidak valid: {$fullLabel}");
        }

        [$jurusan, $urutNumber] = self::parseClassCode(strtoupper($classCode));
        $kelasUrut = str_pad((string) ($urutNumber ?: 1), 2, '0', STR_PAD_LEFT);
        $angkatan = self::resolveAngkatan($tingkat);
        $tingkatLabel = self::TINGKAT_LABEL[$tingkat];

        return [
            'tingkat' => $tingkat,
            'jurusan' => $jurusan,
            'kelas_urut' => $kelasUrut,
            'angkatan' => $angkatan,
            'kelas' => trim($tingkatLabel . ' ' . $jurusan . ($urutNumber ? ' ' . $urutNumber : '')),
        ];
    }

    /**
     * Menghasilkan nomor rekening siswa: <JURUSAN>-<ANGKATAN>-<KELAS_URUT>-<PRESENSI>
     * Presensi otomatis (auto-increment) berdasarkan jumlah siswa yang sudah terdaftar
     * pada kombinasi jurusan + angkatan + kelas_urut yang sama.
     */
    public static function generate(string $jurusan, int $angkatan, string $kelasUrut): string
    {
        $jurusan = strtoupper($jurusan);
        $kelasUrut = str_pad($kelasUrut, 2, '0', STR_PAD_LEFT);

        $existingCount = User::where('jurusan', $jurusan)
            ->where('angkatan', $angkatan)
            ->where('kelas_urut', $kelasUrut)
            ->count();

        do {
            $existingCount++;
            $presensi = str_pad((string) $existingCount, 2, '0', STR_PAD_LEFT);
            $accountNumber = "{$jurusan}-{$angkatan}-{$kelasUrut}-{$presensi}";
        } while (Account::where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    /**
     * Menghasilkan nomor rekening untuk nasabah Guru/Karyawan: <JABATAN>-<NIP>
     * (GRU untuk Guru, KAR untuk Karyawan/Staf).
     */
    public static function generateStaff(string $jabatanKode, string $nip): string
    {
        $accountNumber = strtoupper($jabatanKode) . '-' . preg_replace('/\s+/', '', $nip);

        if (Account::where('account_number', $accountNumber)->exists()) {
            throw new \RuntimeException("Nomor rekening {$accountNumber} sudah terdaftar (NIP duplikat).");
        }

        return $accountNumber;
    }
}
