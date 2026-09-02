<?php

namespace App\Mail;

use App\Models\MaintenanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RichiestaEsternaAssegnata extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MaintenanceRequest $richiesta,
        public string $manutentoreNome,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Richiesta di manutenzione #'.$this->richiesta->id.' assegnata',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.richiesta_assegnata');
    }
}
