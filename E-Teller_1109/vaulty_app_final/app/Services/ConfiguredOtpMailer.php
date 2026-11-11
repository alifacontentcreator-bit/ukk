<?php
/* Tujuan file: Service ini menampung proses bisnis bersama untuk fitur configured otp mailer agar dapat dipakai konsisten oleh beberapa bagian aplikasi. */


namespace App\Services;

use App\Mail\SystemNotification;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Pengirim email sistem: kode aktivasi nasabah, kode OTP lupa password,
 * tautan atur ulang sandi, dan email uji SMTP.
 *
 * Sumber pengiriman dicoba berurutan agar hosting tetap bisa mengirim email
 * walaupun `.env` di hosting belum diisi lengkap:
 *
 *   1. SMTP dari `.env` (`MAIL_MAILER=smtp` + `MAIL_HOST`, `MAIL_USERNAME`, ...).
 *   2. SMTP dari menu Admin > Konfigurasi Sistem (pengaturan `otp_smtp_*`).
 *   3. Fungsi `mail()` milik hosting sebagai cadangan terakhir, memakai alamat
 *      pengirim pada domain aplikasi supaya tidak melanggar SPF/DKIM.
 *
 * Setiap kegagalan dicatat ke `storage/logs/laravel.log` beserta pesan aslinya
 * (port diblokir, sandi aplikasi salah, skema TLS tidak didukung, dan
 * sebagainya) sehingga Administrator dapat memperbaiki penyebabnya.
 */
class ConfiguredOtpMailer
{
    private const DEFAULT_FROM_NAME = 'LKM Mitra Siswa Abadi';

    /** Batas waktu koneksi SMTP agar permintaan API tidak menggantung. */
    private const SMTP_TIMEOUT_SECONDS = 15;

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

        $environment = (array) config('mail.mailers.smtp');
        $environmentIsReady = config('mail.default') === 'smtp'
            && filled($environment['host'] ?? null)
            && filled($environment['username'] ?? null)
            && filled($environment['password'] ?? null)
            && filled(config('mail.from.address'));

        // Semua penyebab kegagalan dikumpulkan agar pesan pada log (dan
        // halaman Admin) menyebutkan alasan sebenarnya, bukan hanya "gagal".
        $failures = [];

        // 1) SMTP yang diisi pada .env hosting.
        if ($environmentIsReady) {
            try {
                $this->sendUsingSmtp($recipient, $notification, [
                    'host' => (string) $environment['host'],
                    'port' => (int) ($environment['port'] ?? 587),
                    'scheme' => $environment['scheme'] ?? null,
                    'username' => (string) $environment['username'],
                    'password' => (string) $environment['password'],
                    'from_address' => (string) config('mail.from.address'),
                    'from_name' => (string) (config('mail.from.name') ?: self::DEFAULT_FROM_NAME),
                ]);

                return;
            } catch (Throwable $exception) {
                $failures[] = 'SMTP .env: ' . $exception->getMessage();
                $this->logFailure('SMTP .env', $exception);
            }
        }

        // 2) SMTP yang disimpan Administrator lewat menu Konfigurasi Sistem.
        if (($settings['otp_smtp_enabled'] ?? '0') === '1') {
            try {
                $this->sendUsingSmtp(
                    $recipient,
                    $notification,
                    $this->databaseSmtpConfig($settings)
                );

                return;
            } catch (Throwable $exception) {
                $failures[] = 'SMTP Konfigurasi Sistem: ' . $exception->getMessage();
                $this->logFailure('SMTP Konfigurasi Sistem', $exception);
            }
        }

        // 3) Cadangan terakhir: fungsi mail() milik hosting, berguna ketika
        //    hosting memblokir koneksi keluar ke port SMTP 465/587.
        try {
            $this->sendUsingHostMail($recipient, $notification);

            return;
        } catch (Throwable $exception) {
            $failures[] = 'mail() hosting: ' . $exception->getMessage();
            $this->logFailure('mail() hosting', $exception);
        }

        throw new RuntimeException(
            'Email belum dapat dikirim. ' . implode(' | ', $failures)
        );
    }

    /**
     * Menyusun data SMTP dari tabel `system_settings` (menu Konfigurasi Sistem).
     *
     * @param  \Illuminate\Support\Collection  $settings
     * @return array<string, mixed>
     */
    private function databaseSmtpConfig($settings): array
    {
        $encryptedPassword = (string) ($settings['otp_smtp_password'] ?? '');
        $host = trim((string) ($settings['otp_smtp_host'] ?? ''));
        $fromAddress = trim((string) ($settings['otp_smtp_from_address'] ?? ''));

        if ($host === '' || $fromAddress === '' || $encryptedPassword === '') {
            throw new RuntimeException(
                'Konfigurasi SMTP belum lengkap (host, sandi, atau alamat pengirim kosong).'
            );
        }

        try {
            $password = Crypt::decryptString($encryptedPassword);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Sandi SMTP tidak dapat dibuka. Simpan ulang pengaturan SMTP.',
                0,
                $exception
            );
        }

        return [
            'host' => $host,
            'port' => (int) ($settings['otp_smtp_port'] ?? 587),
            'scheme' => ($settings['otp_smtp_scheme'] ?? 'starttls') === 'smtps' ? 'smtps' : null,
            'username' => (string) ($settings['otp_smtp_username'] ?? ''),
            'password' => $password,
            'from_address' => $fromAddress,
            'from_name' => trim((string) ($settings['otp_smtp_from_name'] ?? '')) ?: self::DEFAULT_FROM_NAME,
        ];
    }

    /**
     * Mengirim email memakai transport SMTP dengan konfigurasi tertentu.
     *
     * @param  array<string, mixed>  $config
     */
    private function sendUsingSmtp(string $recipient, SystemNotification $notification, array $config): void
    {
        $port = (int) ($config['port'] ?? 587);

        // Skema TLS ditentukan dari port, bukan dari teks bebas pada .env.
        // Port 465 memakai TLS langsung (smtps), port lain memakai STARTTLS
        // otomatis. Nilai lain (mis. MAIL_SCHEME=starttls) ditolak Symfony
        // dengan UnsupportedSchemeException, jadi tidak pernah diteruskan.
        $scheme = ($port === 465 || ($config['scheme'] ?? null) === 'smtps') ? 'smtps' : null;

        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $config['host'],
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => ($config['username'] ?? '') !== '' ? $config['username'] : null,
            'mail.mailers.smtp.password' => $config['password'] ?? null,
            'mail.mailers.smtp.timeout' => self::SMTP_TIMEOUT_SECONDS,
            'mail.from.address' => $config['from_address'],
            'mail.from.name' => $config['from_name'],
        ]);

        Mail::forgetMailers();

        $this->send($recipient, $notification, 'smtp');
    }

    /**
     * Mengirim email lewat fungsi mail() milik hosting (transport `mail`).
     */
    private function sendUsingHostMail(string $recipient, SystemNotification $notification): void
    {
        if (!function_exists('mail')) {
            throw new RuntimeException('Fungsi mail() tidak tersedia pada server ini.');
        }

        config([
            'mail.mailers.host_mail' => ['transport' => 'mail'],
            'mail.from.address' => $this->fallbackFromAddress(),
            'mail.from.name' => (string) (config('mail.from.name') ?: self::DEFAULT_FROM_NAME),
        ]);

        Mail::forgetMailers();

        $this->send($recipient, $notification, 'host_mail');
    }

    /**
     * Alamat pengirim untuk transport mail(): sebaiknya memakai domain
     * aplikasi sendiri agar SPF/DKIM hosting tetap cocok dan email tidak
     * langsung dianggap spam.
     */
    private function fallbackFromAddress(): string
    {
        $configured = trim((string) config('mail.fallback_from_address', ''));

        if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($host !== ''
            && $host !== 'localhost'
            && str_contains($host, '.')
            && !filter_var($host, FILTER_VALIDATE_IP)) {
            return 'no-reply@' . $host;
        }

        return 'no-reply@' . (gethostname() ?: 'localhost');
    }

    /**
     * Membangun ulang mailable lalu mengirimkannya memakai mailer terpilih.
     */
    private function send(string $recipient, SystemNotification $notification, string $mailer): void
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) (config('mail.from.name') ?: self::DEFAULT_FROM_NAME);

        // Identitas pengirim dibuat konsisten dengan akun pengirim: alamat
        // pengirim, Reply-To pada domain yang sama, dan Message-ID berdomain
        // pengirim (bukan hostname server yang justru menaikkan skor spam).
        $domain = str_contains($fromAddress, '@')
            ? substr($fromAddress, strrpos($fromAddress, '@') + 1)
            : '';
        $domain = preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $domain) ? $domain : 'localhost';
        $messageId = bin2hex(random_bytes(12)) . '@' . $domain;

        // Salinan baru untuk setiap percobaan supaya header Message-ID dan
        // Reply-To tidak menumpuk ketika pengiriman dicoba lebih dari sekali.
        $mailable = clone $notification;

        $mailable
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

        Mail::mailer($mailer)->to($recipient)->send($mailable);
    }

    /**
     * Mencatat alasan kegagalan agar dapat diperiksa pada
     * `storage/logs/laravel.log` di hosting.
     */
    private function logFailure(string $channel, Throwable $exception): void
    {
        Log::warning('Pengiriman email lewat ' . $channel . ' gagal.', [
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

}
