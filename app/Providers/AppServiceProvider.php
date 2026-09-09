<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Date leggibili in italiano ("2 minuti fa", ecc.)
        Carbon::setLocale('it');

        // Dietro un tunnel/reverse proxy con HTTPS pubblico (es. Cloudflare)
        // forza la generazione di URL https. Si attiva solo con FORCE_HTTPS=true
        // nel .env, così l'accesso interno in http continua a funzionare finché
        // non si completa il passaggio all'indirizzo pubblico.
        if (config('manutenzione.force_https')) {
            URL::forceScheme('https');
        }
    }
}
