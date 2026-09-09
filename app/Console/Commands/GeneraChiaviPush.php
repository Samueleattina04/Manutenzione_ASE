<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

class GeneraChiaviPush extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Genera le chiavi VAPID per le notifiche push (da incollare nel .env).';

    public function handle(): int
    {
        $keys = WebPush::generateVapidKeys();
        $subject = 'mailto:'.config('mail.from.address', 'manutenzionease@gmail.com');

        $this->info('Chiavi VAPID generate. Incolla queste righe nel file .env:');
        $this->newLine();
        $this->line('WEBPUSH_PUBLIC_KEY='.$keys['public']);
        $this->line('WEBPUSH_PRIVATE_KEY='.$keys['private_b64']);
        $this->line('WEBPUSH_SUBJECT='.$subject);
        $this->newLine();
        $this->warn('Poi esegui: php artisan config:cache  (e riavvia il sito).');
        $this->warn('Conserva la chiave privata: NON condividerla e non pubblicarla.');

        return self::SUCCESS;
    }
}
