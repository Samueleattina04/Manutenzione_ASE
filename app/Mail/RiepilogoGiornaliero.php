<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RiepilogoGiornaliero extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $destinatario,
        public Collection $richieste,
        public bool $isAdmin,
        public string $dataOggi,
        public string $fileContent,
        public string $fileName,
        public string $fileMime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Riepilogo richieste di manutenzione aperte — '.$this->dataOggi,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.riepilogo_giornaliero');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->fileContent, $this->fileName)
                ->withMime($this->fileMime),
        ];
    }
}
