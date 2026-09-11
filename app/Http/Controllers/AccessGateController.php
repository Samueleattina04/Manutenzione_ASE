<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAccessCode;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessGateController extends Controller
{
    public function form(): View
    {
        return view('access.gate');
    }

    public function submit(Request $request): RedirectResponse
    {
        $request->validate([
            'codice' => ['required', 'string'],
        ], [
            'codice.required' => 'Inserisci il codice di accesso.',
        ]);

        $code = trim((string) Settings::get('access_code', ''));

        if ($code === '' || ! hash_equals($code, trim((string) $request->input('codice')))) {
            return back()->withErrors(['codice' => 'Codice di accesso non corretto.']);
        }

        // Sblocca il dispositivo per un anno (cookie cifrato da Laravel).
        return redirect()->intended(route('richieste.index'))
            ->withCookie(cookie(EnsureAccessCode::COOKIE, EnsureAccessCode::token($code), 60 * 24 * 365));
    }
}
