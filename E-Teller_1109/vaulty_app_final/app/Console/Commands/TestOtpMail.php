<?php

namespace App\Console\Commands;

use App\Services\ConfiguredOtpMailer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Memeriksa pengiriman email OTP langsung dari terminal hosting.
 *
 * Gunanya untuk memastikan hosting benar-benar dapat mengirim email tanpa
 * harus menunggu nasabah meminta kode aktivasi:
 *
 *   php artisan mail:test alamat@gmail.com
 *
 * Bila gagal, penyebab sebenarnya (port diblokir, sandi aplikasi salah,
 * skema TLS tidak didukung, dan sebagainya) ditampilkan sekaligus dicatat
 * pada storage/logs/laravel.log.
 */
class TestOtpMail extends Command
{
    protected $signature = 'mail:test
                            {email : Alamat tujuan email uji}';

    protected $description = 'Kirim email uji memakai SMTP aktif (Konfigurasi Sistem atau .env) untuk memastikan hosting dapat mengirim email';

    public function handle(ConfiguredOtpMailer $mailer): int
    {
        $recipient = trim((string) $this->argument('email'));

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email tidak valid: ' . $recipient);
            return self::FAILURE;
        }

        $this->line('MAIL_MAILER : ' . (config('mail.default') ?: '(kosong)'));
        $this->line('MAIL_HOST   : ' . (config('mail.mailers.smtp.host') ?: '(kosong)')
            . ':' . (config('mail.mailers.smtp.port') ?: '(kosong)'));
        $this->line('MAIL_USER   : ' . (config('mail.mailers.smtp.username') ?: '(kosong)'));
        $this->line('MAIL_FROM   : ' . (config('mail.from.address') ?: '(kosong)'));
        $this->newLine();

        try {
            $mailer->sendTestEmail($recipient);
        } catch (Throwable $exception) {
            $this->error('Email uji gagal dikirim: ' . $exception->getMessage());
            $this->line('Rincian tiap percobaan dicatat pada storage/logs/laravel.log.');
            return self::FAILURE;
        }

        $this->info('Email uji berhasil dikirim ke ' . $recipient . '. Periksa kotak masuk dan folder Spam.');

        return self::SUCCESS;
    }
}
