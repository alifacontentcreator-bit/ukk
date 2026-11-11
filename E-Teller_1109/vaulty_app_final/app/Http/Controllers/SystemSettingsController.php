<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\SystemSetting;
use App\Services\AccountNumberGenerator;
use App\Services\ConfiguredOtpMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SystemSettingsController extends Controller
{
    //Menampilkan halaman konfigurasi
    public function index()
    {
    
        $settings = [
            'qr_enabled' => $this->getSetting('qr_enabled', '1'),
            'qr_device' => $this->getSetting('qr_device', 'hid'),
            'qr_mode' => $this->getSetting('qr_mode', 'scanner_manual'),
            'qr_manual_fallback' => $this->getSetting('qr_manual_fallback', '1'),
            'min_balance' => $this->getSetting('min_balance', '10000'),
            'intake_year_kelas_x' => $this->getSetting(AccountNumberGenerator::SETTING_INTAKE_YEAR_X, date('Y')),
            'otp_smtp_enabled' => $this->getSetting('otp_smtp_enabled', config('mail.default') === 'smtp' ? '1' : '0'),
            'otp_smtp_host' => $this->getSetting('otp_smtp_host', config('mail.mailers.smtp.host', '')),
            'otp_smtp_port' => $this->getSetting('otp_smtp_port', (string) config('mail.mailers.smtp.port', 587)),
            'otp_smtp_scheme' => $this->getSetting('otp_smtp_scheme', config('mail.mailers.smtp.scheme') === 'smtps' ? 'smtps' : 'starttls'),
            'otp_smtp_username' => $this->getSetting('otp_smtp_username', config('mail.mailers.smtp.username', '')),
            'otp_smtp_from_address' => $this->getSetting('otp_smtp_from_address', config('mail.from.address', '')),
            'otp_smtp_from_name' => $this->getSetting('otp_smtp_from_name', config('mail.from.name', 'LKM Mitra Siswa Abadi')),
            'otp_smtp_password_saved' => SystemSetting::where('key', 'otp_smtp_password')->exists() || (config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.password'))),
        ];
        if (!in_array($settings['qr_device'], ['hid', 'bluetooth', 'camera'], true)) {
            $settings['qr_device'] = 'hid';
        }

        // Daftar nasabah untuk pengelolaan QR identitas (dibuat Administrator)
        $nasabahList = Account::with('user')->latest()->get();

        return view('admin.konfigurasi', compact('settings', 'nasabahList'));
    }
    // Memvalidasi dan menyimpan konfigurasi sistem, termasuk pengujian pengiriman email SMTP untuk OTP.

    public function update(Request $request, ConfiguredOtpMailer $mailService)
    {
        $passwordIsSaved = SystemSetting::where('key', 'otp_smtp_password')->exists()
            || (config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.password')));
        $validated = $request->validate([
            // Validasi pengaturan QR identitas dan email SMTP untuk OTP
            'qr_enabled' => ['required', 'boolean'],

            'qr_device' => [
                'required',
                'in:hid,bluetooth,camera',
            ],
            // Mode QR: scanner_manual = scanner + manual fallback, scanner_only = scanner only, manual_only = manual only

            'qr_mode' => [
                'required',
                'in:scanner_manual,scanner_only,manual_only',
            ],
            // Apakah fallback manual diizinkan saat scanner tidak tersedia atau gagal

            'qr_manual_fallback' => [
                'required',
                'boolean',
            ],
            // Minimum saldo awal rekening baru (dalam rupiah)

            'min_balance' => [
                'required',
                'integer',
                'min:10000',
            ],
            // Tahun angkatan untuk kelas X (4 digit, misal 2023)

            'intake_year_kelas_x' => [
                'required',
                'digits:4',
                'integer',
            ],
            // Pengaturan SMTP untuk pengiriman OTP melalui email
            'otp_smtp_enabled' => ['required', 'boolean'],
            'otp_smtp_host' => ['nullable', 'required_if:otp_smtp_enabled,1', 'string', 'max:255'],
            'otp_smtp_port' => ['nullable', 'required_if:otp_smtp_enabled,1', 'integer', 'between:1,65535'],
            'otp_smtp_scheme' => ['nullable', 'in:starttls,smtps'],
            'otp_smtp_username' => ['nullable', 'required_if:otp_smtp_enabled,1', 'string', 'max:255'],
            'otp_smtp_password' => [$passwordIsSaved ? 'nullable' : 'required_if:otp_smtp_enabled,1', 'nullable', 'string', 'max:255'],
            'otp_smtp_from_address' => ['nullable', 'required_if:otp_smtp_enabled,1', 'email', 'max:255'],
            'otp_smtp_from_name' => ['nullable', 'string', 'max:255'],
            'test_email' => ['nullable', 'email', 'max:255', 'required_if:action,test_email'],
            'action' => ['nullable', 'in:test_email'],
        ]);
        // Simpan pengaturan sistem ke database

        $this->saveSetting(
            'qr_enabled',
            $validated['qr_enabled']
        );
        // Simpan pengaturan sistem ke database

        $this->saveSetting(
            'qr_device',
            $validated['qr_device']
        );
        // Simpan pengaturan sistem ke database

        $this->saveSetting(
            'qr_mode',
            $validated['qr_mode']
        );
        // Simpan pengaturan sistem ke database

        $this->saveSetting(
            'qr_manual_fallback',
            $validated['qr_manual_fallback']
        );
        // Simpan pengaturan sistem ke database

        $this->saveSetting('min_balance', $validated['min_balance']);
        $this->saveSetting(
            \App\Services\AccountNumberGenerator::SETTING_INTAKE_YEAR_X,
            $validated['intake_year_kelas_x']
        );
        // Simpan pengaturan sistem ke database
        foreach (['otp_smtp_enabled', 'otp_smtp_host', 'otp_smtp_port', 'otp_smtp_scheme', 'otp_smtp_username', 'otp_smtp_from_address', 'otp_smtp_from_name'] as $key) {
            $this->saveSetting($key, $validated[$key] ?? '');
        }
        if (!empty($validated['otp_smtp_password'])) {
            $this->saveSetting('otp_smtp_password', Crypt::encryptString($validated['otp_smtp_password']));
        } elseif ($validated['otp_smtp_enabled'] && !SystemSetting::where('key', 'otp_smtp_password')->exists() && filled(config('mail.mailers.smtp.password'))) {
            $this->saveSetting('otp_smtp_password', Crypt::encryptString(config('mail.mailers.smtp.password')));
        }
        // Jika tombol "Kirim Email Uji" ditekan, kirim email uji ke alamat yang diberikan dan tangani kesalahan pengiriman.

        if (($validated['action'] ?? null) === 'test_email') {
            try {
                $mailService->sendTestEmail($validated['test_email']);
            } catch (\Throwable $exception) {
                report($exception);
                return back()->withErrors([
                    'smtp' => 'Email uji gagal dikirim: '
                        . Str::limit($exception->getMessage(), 300)
                        . ' Periksa host, port, enkripsi (465 = SMTPS, 587 = STARTTLS), '
                        . 'nama pengguna, dan sandi aplikasi SMTP. Rincian juga dicatat pada '
                        . 'storage/logs/laravel.log.',
                ])->withInput();
            }

            return back()->with('success', 'Email uji berhasil dikirim. Periksa kotak masuk dan folder spam.');
        }
        // Jika tidak ada tombol "Kirim Email Uji" yang ditekan, simpan pengaturan sistem dan tampilkan pesan sukses.

        return back()->with(
            'success',
            'Konfigurasi sistem berhasil disimpan.'
        );
    }
    // Mengambil nilai pengaturan sistem dari database, atau mengembalikan nilai default jika tidak ada.

    private function getSetting(
        string $key,
        string $default
    ): string {
        return SystemSetting::where('key', $key)
            ->value('value') ?? $default;
    }
    // Menyimpan nilai pengaturan sistem ke database, membuat entri baru jika belum ada.

    private function saveSetting(
        string $key,
        mixed $value
    ): void {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }
}