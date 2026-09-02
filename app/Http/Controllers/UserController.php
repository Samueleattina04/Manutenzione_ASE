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
            'role' => ['required', Rule::in(array_keys(config('manutenzione.ruoli')))],
            'password' => ['required', 'string', 'min:6'],
        ]);

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'role' => $data['role'],
            'password' => $data['password'], // hashed via cast
            'active' => true,
        ]);

        return back()->with('ok', 'Utente creato.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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

        $user->name = $data['name'];
        $user->role = $data['role'];
        $user->active = $request->boolean('active');
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
        $user->active = ! $user->active;
        $user->save();

        return back()->with('ok', $user->active ? 'Utente riattivato.' : 'Utente disattivato.');
    }

    /** L'account condiviso usato dall'accesso libero degli operatori. */
    private function isGuestOperatore(User $user): bool
    {
        return $user->username === config('manutenzione.guest_operator_username', 'operatore');
    }
}
