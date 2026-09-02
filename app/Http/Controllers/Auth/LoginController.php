<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Pagina iniziale: scelta del profilo (operatore / manutentore / admin). */
    public function chooser(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('richieste.index');
        }

        return view('auth.entra');
    }

    /** Passo intermedio dell'accesso operatore: scelta del reparto. */
    public function chooseReparto(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('richieste.index');
        }

        return view('auth.reparto', ['reparti' => \App\Support\Lists::reparti()]);
    }

    /**
     * Accesso libero come operatore: entra senza username e password dopo aver
     * scelto il reparto. Il reparto raggruppa la visibilità delle richieste.
     */
    public function enterAsOperatore(Request $request): RedirectResponse
    {
        $reparti = \App\Support\Lists::reparti();
        $data = $request->validate([
            'reparto' => ['required', 'string', 'in:'.implode(',', $reparti)],
        ], [
            'reparto.required' => 'Seleziona il reparto.',
            'reparto.in' => 'Reparto non valido.',
        ]);

        $username = config('manutenzione.guest_operator_username', 'operatore');
        $operatore = User::where('username', $username)->where('active', true)->first();

        if (! $operatore) {
            return redirect()->route('login')
                ->withErrors(['username' => "Accesso operatore non disponibile: contatta l'amministratore."]);
        }

        Auth::login($operatore);
        $request->session()->regenerate();
        // Reparto d'accesso: usato solo per filtrare la visibilità.
        $request->session()->put('op_reparto', $data['reparto']);

        return redirect()->route('richieste.index');
    }

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('richieste.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Login per username, solo utenti attivi.
        $ok = Auth::attempt(
            ['username' => $credentials['username'], 'active' => 1, 'password' => $credentials['password']],
            $request->boolean('remember')
        );

        if (! $ok) {
            throw ValidationException::withMessages([
                'username' => 'Credenziali non valide.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('richieste.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('entra');
    }
}
