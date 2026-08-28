<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\MaintenanceRequest;
use App\Models\RequestUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RequestController extends Controller
{
    /** Elenco richieste + statistiche. */
    public function index(Request $request): View
    {
        return view('requests.index', [
            'requests' => $this->filtered($request)->get(),
            'stats' => $this->stats($request),
            'filters' => $this->filterValues($request),
        ]);
    }

    /** Frammento HTML dell'elenco (usato dall'aggiornamento automatico). */
    public function listFragment(Request $request): View
    {
        return view('requests.partials.list', [
            'requests' => $this->filtered($request)->get(),
            'stats' => $this->stats($request),
        ]);
    }

    public function create(): View
    {
        return view('requests.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $impianti = config('manutenzione.impianti');
        $reparti = config('manutenzione.reparti');
        $priorita = array_keys(config('manutenzione.priorita'));

        $data = $request->validate([
            'impianto' => ['required', 'string', 'in:'.implode(',', array_merge($impianti, ['Altro']))],
            'impianto_altro' => ['nullable', 'string', 'max:255', 'required_if:impianto,Altro'],
            'macchinario' => ['required', 'string', 'max:255'],
            'reparto' => ['nullable', 'string', 'in:'.implode(',', $reparti)],
            'descrizione' => ['nullable', 'string'],
            'priorita' => ['required', 'string', 'in:'.implode(',', $priorita)],
            'note' => ['nullable', 'string'],
            'operatore' => ['required', 'string', 'max:255'],
            'foto' => ['nullable', 'array', 'max:8'],
            'foto.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif', 'max:15360'],
        ], [
            'impianto.required' => "Scegli l'impianto.",
            'impianto_altro.required_if' => "Specifica l'impianto (campo Altro).",
            'macchinario.required' => "Inserisci l'impianto o macchinario in questione.",
            'operatore.required' => 'Il campo Operatore è obbligatorio.',
        ]);

        $req = new MaintenanceRequest($data);
        $req->status = 'aperta';
        $req->created_by = $request->user()->id;
        if ($data['impianto'] !== 'Altro') {
            $req->impianto_altro = null;
        }
        // L'operatore è anonimo (account condiviso): leghiamo la richiesta al
        // reparto scelto all'accesso, così tutti gli operatori di quel reparto
        // la vedono (anche da un altro dispositivo o dopo il logout).
        if ($request->user()->isOperatore()) {
            $req->reparto_accesso = $this->operatorReparto($request);
        }
        $req->save();

        $this->storePhotos($request, $req, 'problema');

        return redirect()
            ->route('richieste.show', $req)
            ->with('ok', 'Richiesta inviata con successo.');
    }

    public function show(Request $request, MaintenanceRequest $richiesta): View
    {
        $this->guardOperatorAccess($request, $richiesta);

        $richiesta->load([
            'creator', 'assignee',
            'updates.user', 'updates.attachments',
            'attachments',
        ]);

        return view('requests.show', ['req' => $richiesta]);
    }

    /** Frammento della cronologia (aggiornamento automatico del dettaglio). */
    public function timelineFragment(Request $request, MaintenanceRequest $richiesta): View
    {
        $this->guardOperatorAccess($request, $richiesta);

        $richiesta->load(['creator', 'assignee', 'updates.user', 'updates.attachments', 'attachments']);

        return view('requests.partials.detail_body', ['req' => $richiesta]);
    }

    /** Aggiornamento di stato / intervento (solo manutentore/admin). */
    public function storeUpdate(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        $statiValidi = config('manutenzione.stati_manutentore');

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', $statiValidi)],
            'note' => ['nullable', 'string'],
            'foto' => ['nullable', 'array', 'max:8'],
            'foto.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif', 'max:15360'],
        ]);

        $hasFiles = $request->hasFile('foto');
        if (empty($data['status']) && empty($data['note']) && ! $hasFiles) {
            return back()->withErrors(['note' => 'Inserisci un aggiornamento: stato, descrizione o foto.']);
        }

        DB::transaction(function () use ($request, $richiesta, $data, $hasFiles) {
            $update = RequestUpdate::create([
                'maintenance_request_id' => $richiesta->id,
                'user_id' => $request->user()->id,
                'status' => $data['status'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            if (! empty($data['status'])) {
                $richiesta->status = $data['status'];
                if (! $richiesta->assigned_to) {
                    $richiesta->assigned_to = $request->user()->id;
                }
                if (! $richiesta->taken_at) {
                    $richiesta->taken_at = now();
                }
                $richiesta->resolved_at = config("manutenzione.stati.{$data['status']}.done") ? now() : null;
            }
            $richiesta->save();

            if ($hasFiles) {
                $this->storePhotos($request, $richiesta, 'soluzione', $update->id);
            }
        });

        return redirect()
            ->route('richieste.show', $richiesta)
            ->with('ok', 'Aggiornamento salvato.');
    }

    /** Aggiunta foto a una richiesta esistente. */
    public function storeAttachment(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        $this->guardOperatorAccess($request, $richiesta);

        $request->validate([
            'foto' => ['required', 'array', 'max:8'],
            'foto.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif', 'max:15360'],
        ]);

        $kind = $request->user()->canManutentore() ? 'soluzione' : 'problema';
        $this->storePhotos($request, $richiesta, $kind);
        $richiesta->touch();

        return redirect()->route('richieste.show', $richiesta)->with('ok', 'Foto aggiunte.');
    }

    /** Elimina definitivamente una richiesta (con foto e cronologia). */
    public function destroy(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        abort_unless($richiesta->deletableBy($request->user()), 403, 'Permesso negato');

        // Rimuove i file fisici delle foto prima di cancellare i record.
        $paths = $richiesta->attachments()->pluck('path')->all();
        if ($paths) {
            Storage::disk('local')->delete($paths);
        }

        // La cancellazione propaga a interventi e allegati (foreign key cascade).
        $richiesta->delete();

        return redirect()->route('richieste.index')->with('ok', 'Richiesta eliminata.');
    }

    // ---- helpers ---------------------------------------------------------

    private function storePhotos(Request $request, MaintenanceRequest $req, string $kind, ?int $updateId = null): void
    {
        foreach ((array) $request->file('foto', []) as $file) {
            if (! $file) {
                continue;
            }
            $path = $file->store('uploads', 'local');
            Attachment::create([
                'maintenance_request_id' => $req->id,
                'request_update_id' => $updateId,
                'kind' => $kind,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function filtered(Request $request): Builder
    {
        $f = $this->filterValues($request);

        return MaintenanceRequest::query()
            ->with('assignee')
            ->withCount('attachments')
            // L'operatore vede solo le richieste del reparto scelto all'accesso.
            ->when(
                $request->user()->isOperatore(),
                fn ($q) => $q->where('reparto_accesso', $this->operatorReparto($request))
            )
            ->when($f['status'] === 'attive', fn ($q) => $q->whereNotIn('status', ['risolta', 'chiusa']))
            ->when($f['status'] === 'chiuse', fn ($q) => $q->whereIn('status', ['risolta', 'chiusa']))
            ->when(
                ! in_array($f['status'], ['attive', 'chiuse', 'tutte', ''], true),
                fn ($q) => $q->where('status', $f['status'])
            )
            ->when($f['priorita'] !== '', fn ($q) => $q->where('priorita', $f['priorita']))
            ->when($f['impianto'] !== '', fn ($q) => $q->where('impianto', $f['impianto']))
            ->when($f['mine'], fn ($q) => $q->where('created_by', $request->user()->id))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $like = '%'.$f['q'].'%';
                $q->where(function ($w) use ($like) {
                    $w->where('macchinario', 'like', $like)
                        ->orWhere('descrizione', 'like', $like)
                        ->orWhere('reparto', 'like', $like)
                        ->orWhere('operatore', 'like', $like)
                        ->orWhere('note', 'like', $like);
                });
            })
            ->orderByRaw("CASE WHEN status IN ('risolta','chiusa') THEN 2 ELSE 1 END asc")
            ->orderByRaw("FIELD(priorita,'rosso','giallo','verde') asc")
            ->orderByDesc('created_at');
    }

    private function filterValues(Request $request): array
    {
        return [
            'status' => (string) $request->query('status', 'attive'),
            'priorita' => (string) $request->query('priorita', ''),
            'impianto' => (string) $request->query('impianto', ''),
            'q' => trim((string) $request->query('q', '')),
            'mine' => $request->boolean('mine'),
        ];
    }

    private function stats(Request $request): array
    {
        // Per gli operatori i conteggi sono limitati al reparto d'accesso.
        $reparto = $request->user()->isOperatore() ? $this->operatorReparto($request) : null;
        $base = fn () => $reparto === null
            ? MaintenanceRequest::query()
            : MaintenanceRequest::where('reparto_accesso', $reparto);

        return [
            'attive' => $base()->whereNotIn('status', ['risolta', 'chiusa'])->count(),
            'urgenti' => $base()->where('priorita', 'rosso')->whereNotIn('status', ['risolta', 'chiusa'])->count(),
            'mie' => $reparto === null
                ? MaintenanceRequest::where('created_by', $request->user()->id)->count()
                : $base()->count(),
        ];
    }

    /** Reparto scelto dall'operatore all'accesso (dalla sessione). */
    private function operatorReparto(Request $request): string
    {
        return (string) $request->session()->get('op_reparto', '');
    }

    /** Blocca l'operatore che tenta di vedere una richiesta di un altro reparto. */
    private function guardOperatorAccess(Request $request, MaintenanceRequest $richiesta): void
    {
        if ($request->user()->isOperatore()
            && $richiesta->reparto_accesso !== $this->operatorReparto($request)) {
            abort(404);
        }
    }
}
