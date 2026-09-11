<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Porta d'ingresso" dell'app: se è impostato un codice di accesso aziendale,
 * ogni dispositivo deve inserirlo una volta. Superato il codice, viene salvato
 * un cookie (cifrato) di lunga durata, così non viene più richiesto.
 * Se nessun codice è impostato, la porta è disattivata e l'app è aperta.
 */
class EnsureAccessCode
{
    public const COOKIE = 'accesso_ok';

    public function handle(Request $request, Closure $next): Response
    {
        $code = trim((string) Settings::get('access_code', ''));

        // Funzione disattivata (nessun codice) → nessun filtro.
        if ($code === '') {
            return $next($request);
        }

        // Le pagine del codice stesso e l'health-check non vanno filtrate.
        if ($request->routeIs('accesso.*') || $request->is('up')) {
            return $next($request);
        }

        if (hash_equals(self::token($code), (string) $request->cookie(self::COOKIE))) {
            return $next($request);
        }

        // Non ancora sbloccato: ricorda dove voleva andare e manda alla porta.
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('accesso.form');
    }

    /** Token del cookie derivato dal codice: cambiando il codice si invalidano i vecchi cookie. */
    public static function token(string $code): string
    {
        return substr(hash('sha256', 'porta-accesso|'.$code), 0, 32);
    }
}
