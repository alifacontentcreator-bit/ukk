<?php

namespace App\Services;

use App\Mail\SystemNotification;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ConfiguredOtpMailer
{
    private const DEFAULT_FROM_NAME = 'LKM Mitra Siswa Abadi';

    public function sendTestEmail(string $recipient): void
    {
        $this->deliver($recipient, new SystemNotification(
            subjectLine: 'Uji pengiriman email ' . self::DEFAULT_FROM_NAME,
            title: 'Email uji berhasil dikirim',
            paragraphs: [
                'Pengaturan SMTP ' . self::DEFAULT_FROM_NAME . ' berhasil mengirim email.',
                'Kode aktivasi nasabah dan tautan atur ulang sandi dikirim melalui alamat email ini.',
            ],
            note: 'Email ini hanya untuk menguji konfigurasi. Tidak ada tindakan yang diperlukan.',
        ));
    }

    public function sendCustomerActivationCode(
        string $recipient,
        string $name,
        string $activationCode
    ): void {
        $this->deliver($recipient, new SystemNotification(
            subjectLine: 'Kode aktivasi akun ' . self::DEFAULT_FROM_NAME,
            title: 'Kode aktivasi akun',
            paragraphs: [
                'Halo ' . $name . ',',
                'Masukkan kode berikut pada aplikasi untuk mengaktifkan akun tabungan Anda.',
            ],
            highlight: $activationCode,
            note: 'Kode berlaku selama 3 menit dan hanya dapat digunakan satu kali. Jangan berikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai Administrator.',
        ));
    }

    public function sendCustomerPasswordResetCode(
        string $recipient,
        string $name,
        string $resetCode
    ): void {
        $this->deliver($recipient, new SystemNotification(
            subjectLine: 'Kode OTP lupa password ' . self::DEFAULT_FROM_NAME,
            title: 'Kode OTP lupa password',
            paragraphs: [
                'Halo ' . $name . ',',
                'Kami menerima permintaan untuk mengatur ulang password akun tabungan Anda. Masukkan kode OTP berikut pada aplikasi.',
            ],
            highlight: $resetCode,
            note: 'Kode berlaku selama 3 menit dan hanya dapat digunakan satu kali. Jangan berikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai petugas. Bila Anda tidak meminta perubahan password, abaikan email ini dan password lama tetap berlaku.',
        ));
    }

    public function sendPasswordResetLink(string $recipient, string $name, string $url): void
    {
        $this->deliver($recipient, new SystemNotification(
            subjectLine: 'Atur ulang sandi akun ' . self::DEFAULT_FROM_NAME,
            title: 'Buat sandi baru',
            paragraphs: [
                'Halo ' . $name . ',',
                'Administrator sudah menyetujui permintaan pemulihan sandi akun Anda. Buka tautan berikut untuk membuat sandi baru.',
            ],
            actionText: 'Buat sandi baru',
            actionUrl: $url,
            note: 'Tautan hanya dapat digunakan satu kali dan berlaku selama 30 menit. Bila Anda tidak meminta perubahan sandi, abaikan email ini dan sandi lama tetap berlaku.',
        ));
    }

    private function deliver(string $recipient, SystemNotification $notification): void
    {
        $settings = SystemSetting::whereIn('key', [
            'otp_smtp_enabled',
            'otp_smtp_host',
            'otp_smtp_port',
            'otp_smtp_scheme',
            'otp_smtp_username',
            'otp_smtp_password',
            'otp_smtp_from_address',
            'otp_smtp_from_name',
        ])->pluck('value', 'key');

        $environment = config('mail.mailers.smtp');
        $environmentIsReady = config('mail.default') === 'smtp'
            && filled($environment['host'] ?? null)
            && filled($environment['username'] ?? null)
            && filled($environment['password'] ?? null)
            && filled(config('mail.from.address'));

        if ($environmentIsReady) {
            $host = $environment['host'];
            $port = (int) ($environment['port'] ?? 587);
            $scheme = $environment['scheme'] ?? null;
            $username = $environment['username'];
            $password = $environment['password'];
            $fromAddress = config('mail.from.address');
            $fromName = config('mail.from.name', self::DEFAULT_FROM_NAME);
        } elseif (($settings['otp_smtp_enabled'] ?? '0') === '1') {
            $encryptedPassword = $settings['otp_smtp_password'] ?? '';
            if (!$encryptedPassword || empty($settings['otp_smtp_host']) || empty($settings['otp_smtp_from_address'])) {
                throw new RuntimeException('Konfigurasi SMTP belum lengkap.');
            }
            try {
                $password = Crypt::decryptString($encryptedPassword);
            } catch (\Throwable $exception) {
                throw new RuntimeException('Sandi SMTP tidak dapat dibuka. Simpan ulang pengaturan SMTP.', 0, $exception);
            }
            $host = $settings['otp_smtp_host'];
            $port = (int) ($settings['otp_smtp_port'] ?? 587);
            $scheme = ($settings['otp_smtp_scheme'] ?? 'starttls') === 'smtps' ? 'smtps' : null;
            $username = $settings['otp_smtp_username'] ?? null;
            $fromAddress = $settings['otp_smtp_from_address'];
            $fromName = $settings['otp_smtp_from_name'] ?: self::DEFAULT_FROM_NAME;
        } else {
            throw new RuntimeException('SMTP belum diaktifkan pada Konfigurasi Sistem atau .env.');
        }

        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.timeout' => 15,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        // Identitas pengirim dibuat konsisten dengan akun SMTP: alamat pengirim,
        // Reply-To pada domain yang sama, dan Message-ID berdomain pengirim
        // (bukan hostname komputer lokal yang justru menaikkan skor spam).
        $domain = str_contains($fromAddress, '@')
            ? substr($fromAddress, strrpos($fromAddress, '@') + 1)
            : '';
        $domain = preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $domain) ? $domain : 'localhost';
        $messageId = bin2hex(random_bytes(12)) . '@' . $domain;

        $notification
            ->from($fromAddress, $fromName)
            ->withSymfonyMessage(function ($message) use ($fromAddress, $fromName, $messageId) {
                if ($message instanceof Email) {
                    // Symfony Email tidak menerima nama sebagai argumen kedua.
                    $message->replyTo(new Address($fromAddress, $fromName));
                    $headers = $message->getHeaders();
                    $headers->addIdHeader('Message-ID', $messageId);
                    $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
                }
            });

        Mail::mailer('smtp')->to($recipient)->send($notification);
    }

}
