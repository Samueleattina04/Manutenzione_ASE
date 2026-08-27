<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Consente l'accesso solo agli utenti con uno dei ruoli indicati.
     * Uso: ->middleware('role:manutentore,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Permesso negato');
        }

        return $next($request);
    }
}
