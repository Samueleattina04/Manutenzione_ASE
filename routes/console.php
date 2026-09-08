<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ogni mattina alle 07:00 (ora italiana): riepilogo delle richieste aperte
// via email con allegato Excel. Admin -> tutte; manutentori -> le proprie.
Schedule::command('richieste:riepilogo')
    ->dailyAt('07:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping();
