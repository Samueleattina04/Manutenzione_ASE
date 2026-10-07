<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nota: il riepilogo giornaliero via email (comando `richieste:riepilogo`) non
// viene più pianificato automaticamente, perché le notifiche push in tempo reale
// hanno reso superfluo l'invio quotidiano. Il comando resta disponibile per un
// eventuale invio/export manuale: `php artisan richieste:riepilogo`.
