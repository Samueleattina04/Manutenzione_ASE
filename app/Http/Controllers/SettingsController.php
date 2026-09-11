<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAccessCode;
use App\Models\ListItem;
use App\Support\Lists;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const TYPES = ['impianto', 'reparto'];

    public function index(): View
    {
        return view('settings.index', [
            'impianti' => ListItem::where('type', 'impianto')->orderBy('position')->orderBy('id')->get(),
            'reparti' => ListItem::where('type', 'reparto')->orderBy('position')->orderBy('id')->get(),
            'accessCode' => Settings::get('access_code', ''),
            'operatorPin' => Settings::get('operator_pin', ''),
        ]);
    }

    /** Imposta/rimuove il PIN operatori (richiesto a ogni accesso operatore). */
    public function updateOperatorPin(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Riservato al super-amministratore.');

        $data = $request->validate([
            'pin' => ['nullable', 'string', 'max:20'],
        ]);

        $pin = trim((string) ($data['pin'] ?? ''));
        Settings::set('operator_pin', $pin !== '' ? $pin : null);

        return back()->with('ok', $pin === ''
            ? 'PIN operatori disattivato: gli operatori entrano solo scegliendo il reparto.'
            : 'PIN operatori aggiornato: verrà richiesto a ogni accesso operatore.');
    }

    /** Imposta/rimuove il codice di accesso aziendale (porta d'ingresso). */
    public function updateAccessCode(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Riservato al super-amministratore.');

        $data = $request->validate([
            'codice' => ['nullable', 'string', 'max:100'],
        ]);

        $code = trim((string) ($data['codice'] ?? ''));
        Settings::set('access_code', $code !== '' ? $code : null);

        if ($code === '') {
            return back()->with('ok', "Codice d'accesso disattivato: l'app è aperta a chi ha il link.");
        }

        // Mantiene sbloccato il dispositivo dell'admin che ha appena impostato il codice.
        return back()
            ->with('ok', "Codice d'accesso aggiornato. Gli altri dispositivi dovranno reinserirlo.")
            ->withCookie(cookie(EnsureAccessCode::COOKIE, EnsureAccessCode::token($code), 60 * 24 * 365));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'value' => ['required', 'string', 'max:255'],
        ], ['value.required' => 'Inserisci un valore.']);

        $exists = ListItem::where('type', $data['type'])
            ->whereRaw('LOWER(value) = ?', [mb_strtolower(trim($data['value']))])->exists();
        if ($exists) {
            return back()->withErrors(['value' => 'Valore già presente in questo elenco.']);
        }

        $max = (int) ListItem::where('type', $data['type'])->max('position');
        ListItem::create([
            'type' => $data['type'],
            'value' => trim($data['value']),
            'position' => $max + 1,
        ]);
        Lists::flush();

        return back()->with('ok', 'Voce aggiunta.');
    }

    public function updateItem(Request $request, ListItem $listItem): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:255'],
        ], ['value.required' => 'Inserisci un valore.']);

        $dup = ListItem::where('type', $listItem->type)
            ->where('id', '!=', $listItem->id)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower(trim($data['value']))])->exists();
        if ($dup) {
            return back()->withErrors(['value' => 'Valore già presente in questo elenco.']);
        }

        $listItem->update(['value' => trim($data['value'])]);
        Lists::flush();

        return back()->with('ok', 'Voce aggiornata.');
    }

    public function destroyItem(ListItem $listItem): RedirectResponse
    {
        $listItem->delete();
        Lists::flush();

        return back()->with('ok', 'Voce eliminata.');
    }

    /** Sposta la voce su/giù scambiando la posizione con quella adiacente. */
    public function moveItem(Request $request, ListItem $listItem): RedirectResponse
    {
        $dir = $request->input('dir') === 'up' ? 'up' : 'down';

        $items = ListItem::where('type', $listItem->type)
            ->orderBy('position')->orderBy('id')->get();
        $index = $items->search(fn ($i) => $i->id === $listItem->id);
        $swapWith = $dir === 'up' ? $items->get($index - 1) : $items->get($index + 1);

        if ($swapWith) {
            $p = $listItem->position;
            $listItem->update(['position' => $swapWith->position]);
            $swapWith->update(['position' => $p]);
            // In caso di posizioni uguali, forza un ordine coerente.
            if ($listItem->position === $swapWith->position) {
                $this->normalize($listItem->type);
            }
            Lists::flush();
        }

        return back();
    }

    private function normalize(string $type): void
    {
        $items = ListItem::where('type', $type)->orderBy('position')->orderBy('id')->get();
        foreach ($items as $i => $item) {
            $item->update(['position' => $i]);
        }
    }
}
