<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use App\Support\Lists;
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
        ]);
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
