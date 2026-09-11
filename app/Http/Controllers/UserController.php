<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderBy('role')->orderBy('username')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(config('manutenzione.ruoli_assegnabili'))],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => ($data['email'] ?? null) ?: null,
            'role' => $data['role'],
            'password' => $data['password'], // hashed via cast
            'active' => true,
            // Il flag super-admin può essere assegnato solo da un super-admin, e solo agli admin.
            'is_super_admin' => $request->user()->isSuperAdmin()
                && $data['role'] === 'admin'
                && $request->boolean('is_super_admin'),
        ]);

        return back()->with('ok', 'Utente creato.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(config('manutenzione.ruoli')))],
            'active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        // Un admin non può disattivare o declassare sé stesso.
        if ($user->id === $request->user()->id) {
            $data['role'] = 'admin';
            $data['active'] = true;
        }

        // L'account operatore ad accesso libero deve restare attivo e operatore.
        if ($this->isGuestOperatore($user)) {
            $data['role'] = 'operatore';
            $data['active'] = true;
        }

        // Flag super-admin: solo un super-admin può cambiarlo; vale solo per gli admin.
        $desiredSuper = (bool) $user->is_super_admin;
        if ($data['role'] !== 'admin') {
            $desiredSuper = false; // super-admin ha senso solo per il ruolo admin
        } elseif ($request->user()->isSuperAdmin()) {
            $desiredSuper = $request->boolean('is_super_admin');
        }

        // Deve restare almeno un super-amministratore attivo.
        $perdeSuper = $user->isSuperAdmin() && (! $desiredSuper || ! $request->boolean('active'));
        if ($perdeSuper) {
            $altriSuper = User::where('role', 'admin')->where('is_super_admin', true)
                ->where('active', true)->where('id', '!=', $user->id)->count();
            if ($altriSuper < 1) {
                return back()->withErrors(['user' => 'Deve restare almeno un super-amministratore attivo.']);
            }
        }

        $user->name = $data['name'];
        $user->email = ($data['email'] ?? null) ?: null;
        $user->role = $data['role'];
        $user->active = $request->boolean('active');
        $user->is_super_admin = $desiredSuper;
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('ok', 'Utente aggiornato.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Non puoi disattivare il tuo stesso account.']);
        }
        if ($this->isGuestOperatore($user) && $user->active) {
            return back()->withErrors(['user' => "Non puoi disattivare l'account operatore ad accesso libero."]);
        }
        // Non lasciare l'app senza super-amministratori attivi.
        if ($user->isSuperAdmin() && $user->active && ! $this->altriSuperAttivi($user)) {
            return back()->withErrors(['user' => 'Deve restare almeno un super-amministratore attivo.']);
        }
        $user->active = ! $user->active;
        $user->save();

        return back()->with('ok', $user->active ? 'Utente riattivato.' : 'Utente disattivato.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Non puoi eliminare te stesso.
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Non puoi eliminare il tuo stesso account.']);
        }
        // L'account operatore ad accesso libero non è eliminabile: serve all'accesso libero.
        if ($this->isGuestOperatore($user)) {
            return back()->withErrors(['user' => "Non puoi eliminare l'account operatore ad accesso libero."]);
        }
        // Non eliminare l'ultimo super-amministratore.
        if ($user->isSuperAdmin() && ! $this->altriSuperAttivi($user)) {
            return back()->withErrors(['user' => 'Deve restare almeno un super-amministratore attivo.']);
        }

        // Le richieste, gli aggiornamenti e gli allegati restano (le chiavi esterne
        // verso l'utente sono impostate a NULL alla cancellazione).
        $user->delete();

        return back()->with('ok', 'Utente eliminato.');
    }

    /** L'account condiviso usato dall'accesso libero degli operatori. */
    private function isGuestOperatore(User $user): bool
    {
        return $user->username === config('manutenzione.guest_operator_username', 'operatore');
    }

    /** Esiste almeno un altro super-amministratore attivo (oltre a $user)? */
    private function altriSuperAttivi(User $user): bool
    {
        return User::where('role', 'admin')->where('is_super_admin', true)
            ->where('active', true)->where('id', '!=', $user->id)->exists();
    }
}
