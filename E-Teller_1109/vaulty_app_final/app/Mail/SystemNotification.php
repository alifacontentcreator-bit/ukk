<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email pemberitahuan sistem (kode aktivasi nasabah, tautan atur ulang sandi,
 * dan email uji SMTP).
 *
 * Dikirim sebagai multipart/alternative (HTML + teks polos) agar mudah dibaca
 * sekaligus lebih diterima filter spam dibanding email teks polos berisi URL
 * panjang tanpa struktur.
 */
class SystemNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $paragraphs
     */
    public function __construct(
        public string $subjectLine,
        public string $title,
        public array $paragraphs = [],
        public ?string $highlight = null,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $note = null,
        public ?string $appName = null,
        public string $appFooter = 'SMK Negeri 1 Bantul',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.notification',
            text: 'emails.notification-text',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
