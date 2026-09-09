<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\MaintenanceRequest;
use App\Models\RequestUpdate;
use App\Services\PushNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Mail\RichiestaEsternaAssegnata;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** Esporta in CSV (apribile in Excel) le richieste secondo i filtri correnti. */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)
            ->with(['externalMaintainer', 'updates.user'])
            ->reorder('created_at', 'desc')
            ->get();

        $filename = 'richieste_manutenzione_'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 per Excel

            fputcsv($out, \App\Support\RichiesteExport::headers(), ';');
            foreach ($rows as $r) {
                fputcsv($out, \App\Support\RichiesteExport::row($r), ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $impianti = \App\Support\Lists::impianti();
        $reparti = \App\Support\Lists::reparti();
        $priorita = array_keys(config('manutenzione.priorita'));

        $destinatari = array_keys(config('manutenzione.destinatari'));

        $data = $request->validate([
            'impianto' => ['required', 'string', 'in:'.implode(',', array_merge($impianti, ['Altro']))],
            'impianto_altro' => ['nullable', 'string', 'max:255', 'required_if:impianto,Altro'],
            'macchinario' => ['required', 'string', 'max:255'],
            'reparto' => ['nullable', 'string', 'in:'.implode(',', $reparti)],
            'destinatario' => ['required', 'string', 'in:'.implode(',', $destinatari)],
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
            'destinatario.required' => 'Scegli il destinatario.',
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

        // Notifiche in tempo reale in base al destinatario:
        // - straordinaria: assegnata automaticamente al manutentore straordinario;
        // - interna: avvisa tutti i manutentori interni.
        // (l'esterna viene notificata quando l'admin la assegna)
        if ($req->destinatario === 'straordinaria') {
            $this->assegnaStraordinario($req);
        } elseif ($req->destinatario === 'interna') {
            (new PushNotifier())->nuovaRichiestaInterna($req);
        }

        return redirect()
            ->route('richieste.show', $req)
            ->with('ok', 'Richiesta inviata con successo.');
    }

    public function show(Request $request, MaintenanceRequest $richiesta): View
    {
        $this->guardAccess($request, $richiesta);

        $richiesta->load([
            'creator', 'assignee', 'externalMaintainer',
            'updates.user', 'updates.attachments',
            'attachments',
        ]);

        // Elenco manutentori esterni (l'admin può assegnarli o cambiare il
        // destinatario di una richiesta in "esterna").
        $manutentoriEsterni = $request->user()->isAdmin()
            ? \App\Models\User::where('role', 'manutentore_esterno')->where('active', true)
                ->orderBy('name')->get()
            : collect();

        return view('requests.show', [
            'req' => $richiesta,
            'manutentoriEsterni' => $manutentoriEsterni,
        ]);
    }

    /** Frammento della cronologia (aggiornamento automatico del dettaglio). */
    public function timelineFragment(Request $request, MaintenanceRequest $richiesta): View
    {
        $this->guardAccess($request, $richiesta);

        $richiesta->load(['creator', 'assignee', 'externalMaintainer', 'updates.user', 'updates.attachments', 'attachments']);

        return view('requests.partials.detail_body', ['req' => $richiesta]);
    }

    /** Aggiornamento di stato / intervento (solo manutentore/admin). */
    public function storeUpdate(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        $this->guardAccess($request, $richiesta);

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

        // Notifica in tempo reale agli operatori del reparto (cambio stato / intervento).
        (new PushNotifier())->statoAggiornato($richiesta);

        return redirect()
            ->route('richieste.show', $richiesta)
            ->with('ok', 'Aggiornamento salvato.');
    }

    /** Aggiunta foto a una richiesta esistente. */
    public function storeAttachment(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        $this->guardAccess($request, $richiesta);

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

    /**
     * Query di base con la VISIBILITÀ per ruolo già applicata:
     * - operatore: solo le richieste del reparto scelto all'accesso;
     * - manutentore esterno: solo le richieste esterne assegnate a lui;
     * - manutentore interno / admin: tutte.
     */
    private function visibleQuery(Request $request): Builder
    {
        $user = $request->user();

        return MaintenanceRequest::query()
            // Operatore: solo il reparto scelto all'accesso.
            ->when(
                $user->isOperatore(),
                fn ($q) => $q->where('reparto_accesso', $this->operatorReparto($request))
            )
            // Manutentore interno: tutte le richieste di manutenzione interna.
            ->when(
                $user->isManutentore(),
                fn ($q) => $q->where('destinatario', 'interna')
            )
            // Manutentore esterno: solo le esterne assegnate a lui.
            ->when(
                $user->isManutentoreEsterno(),
                fn ($q) => $q->where('destinatario', 'esterna')
                    ->where('external_maintainer_id', $user->id)
            )
            // Manutentore straordinario: le richieste di manutenzione straordinaria.
            ->when(
                $user->isManutentoreStraordinario(),
                fn ($q) => $q->where('destinatario', 'straordinaria')
            );
    }

    private function filtered(Request $request): Builder
    {
        $f = $this->filterValues($request);

        return $this->visibleQuery($request)
            ->with('assignee')
            ->withCount('attachments')
            ->when($f['status'] === 'attive', fn ($q) => $q->whereNotIn('status', ['risolta', 'chiusa']))
            ->when($f['status'] === 'chiuse', fn ($q) => $q->whereIn('status', ['risolta', 'chiusa']))
            ->when(
                ! in_array($f['status'], ['attive', 'chiuse', 'tutte', ''], true),
                fn ($q) => $q->where('status', $f['status'])
            )
            ->when($f['priorita'] !== '', fn ($q) => $q->where('priorita', $f['priorita']))
            ->when($f['impianto'] !== '', fn ($q) => $q->where('impianto', $f['impianto']))
            // Richieste esterne/straordinarie ancora da assegnare a un manutentore.
            ->when($f['da_assegnare'], fn ($q) => $q->whereIn('destinatario', ['esterna', 'straordinaria'])->whereNull('external_maintainer_id'))
            // Filtro per data di apertura (dal / al inclusi).
            ->when($f['dal'], fn ($q) => $q->whereDate('created_at', '>=', $f['dal']))
            ->when($f['al'], fn ($q) => $q->whereDate('created_at', '<=', $f['al']))
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
            'da_assegnare' => $request->boolean('da_assegnare'),
            'dal' => $this->validDate($request->query('dal')),
            'al' => $this->validDate($request->query('al')),
        ];
    }

    /** Restituisce la data solo se in formato valido (Y-m-d), altrimenti ''. */
    private function validDate($value): string
    {
        $value = (string) $value;
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return ($d && $d->format('Y-m-d') === $value) ? $value : '';
    }

    private function stats(Request $request): array
    {
        // I conteggi rispettano la visibilità del ruolo.
        $scoped = ! $request->user()->isAdmin();

        return [
            'attive' => $this->visibleQuery($request)->whereNotIn('status', ['risolta', 'chiusa'])->count(),
            'urgenti' => $this->visibleQuery($request)->where('priorita', 'rosso')
                ->whereNotIn('status', ['risolta', 'chiusa'])->count(),
            'mie' => $scoped
                ? $this->visibleQuery($request)->count()
                : MaintenanceRequest::where('created_by', $request->user()->id)->count(),
            'da_assegnare' => MaintenanceRequest::whereIn('destinatario', ['esterna', 'straordinaria'])
                ->whereNull('external_maintainer_id')->count(),
        ];
    }

    /** Reparto scelto dall'operatore all'accesso (dalla sessione). */
    private function operatorReparto(Request $request): string
    {
        return (string) $request->session()->get('op_reparto', '');
    }

    /** Blocca chi tenta di vedere una richiesta fuori dalla propria visibilità. */
    private function guardAccess(Request $request, MaintenanceRequest $richiesta): void
    {
        $user = $request->user();

        if ($user->isOperatore()
            && $richiesta->reparto_accesso !== $this->operatorReparto($request)) {
            abort(404);
        }

        if ($user->isManutentore()
            && $richiesta->destinatario !== 'interna') {
            abort(404);
        }

        if ($user->isManutentoreEsterno()
            && ! ($richiesta->destinatario === 'esterna'
                && $richiesta->external_maintainer_id === $user->id)) {
            abort(404);
        }

        if ($user->isManutentoreStraordinario()
            && $richiesta->destinatario !== 'straordinaria') {
            abort(404);
        }
    }

    /** Imposta il tempo di intervento previsto (manutentore/admin). */
    public function setEta(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        abort_unless($request->user()->canManutentore(), 403, 'Permesso negato');
        $this->guardAccess($request, $richiesta);

        $opzioni = array_keys(config('manutenzione.eta_opzioni'));

        $data = $request->validate([
            'eta' => ['required', 'in:'.implode(',', array_merge($opzioni, ['annulla']))],
        ], [
            'eta.required' => 'Scegli entro quanto tempo sarai in reparto.',
            'eta.in' => 'Tempo di intervento non valido.',
        ]);

        if ($data['eta'] === 'annulla') {
            $richiesta->eta_intervento = null;
            $richiesta->save();

            return redirect()->route('richieste.show', $richiesta)
                ->with('ok', 'Tempo di intervento rimosso.');
        }

        $richiesta->eta_intervento = now()->addMinutes((int) $data['eta']);
        $richiesta->save();

        // Notifica agli operatori del reparto il tempo di intervento previsto.
        (new PushNotifier())->tempoIntervento($richiesta);

        return redirect()->route('richieste.show', $richiesta)
            ->with('ok', 'Tempo di intervento previsto aggiornato: '.$richiesta->etaLabel().'.');
    }

    /**
     * Aggiorna il destinatario di una richiesta e la relativa assegnazione (solo admin):
     * - interna: nessun manutentore assegnato (la vedono tutti gli interni);
     * - straordinaria: assegnata automaticamente al manutentore straordinario;
     * - esterna: assegnata al manutentore esterno scelto dall'admin.
     * Se l'assegnazione cambia, invia l'email di notifica al manutentore.
     */
    public function updateAssegnazione(Request $request, MaintenanceRequest $richiesta): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Permesso negato');

        $destinatari = array_keys(config('manutenzione.destinatari'));
        $request->validate([
            'destinatario' => ['required', 'in:'.implode(',', $destinatari)],
        ], [
            'destinatario.required' => 'Scegli il tipo di manutenzione.',
        ]);

        $dest = $request->input('destinatario');
        $precedente = $richiesta->external_maintainer_id;
        $richiesta->destinatario = $dest;
        $redirect = redirect()->route('richieste.show', $richiesta);

        // Manutenzione interna: nessuna assegnazione.
        if ($dest === 'interna') {
            $richiesta->external_maintainer_id = null;
            $richiesta->save();

            return $redirect->with('ok', 'Richiesta impostata come manutenzione interna: visibile a tutti i manutentori interni.');
        }

        // Manutenzione straordinaria: assegnazione automatica al manutentore straordinario.
        if ($dest === 'straordinaria') {
            $straord = $this->manutentoreStraordinario();
            if (! $straord) {
                $richiesta->external_maintainer_id = null;
                $richiesta->save();

                return $redirect->with('ok', 'Richiesta impostata come manutenzione straordinaria.')
                    ->with('warn', 'Nessun manutentore straordinario configurato: crea un utente con quel ruolo in Utenti.');
            }
            $richiesta->external_maintainer_id = $straord->id;
            $richiesta->save();

            if ($richiesta->external_maintainer_id !== $precedente) {
                return $this->redirectConEmail($redirect, $richiesta, $straord);
            }

            return $redirect->with('ok', 'Richiesta assegnata al manutentore straordinario '.$straord->name.'.');
        }

        // Manutenzione esterna: l'admin sceglie il manutentore esterno.
        $data = $request->validate([
            'external_maintainer_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('role', 'manutentore_esterno')->where('active', 1),
            ],
        ], [
            'external_maintainer_id.required' => 'Scegli il manutentore esterno.',
            'external_maintainer_id.exists' => 'Manutentore esterno non valido.',
        ]);

        $richiesta->external_maintainer_id = (int) $data['external_maintainer_id'];
        $richiesta->save();

        if ($richiesta->external_maintainer_id !== $precedente) {
            return $this->redirectConEmail($redirect, $richiesta, $richiesta->externalMaintainer()->first());
        }

        return $redirect->with('ok', 'Manutentore esterno assegnato.');
    }

    /** L'unico manutentore straordinario attivo (se configurato). */
    private function manutentoreStraordinario(): ?\App\Models\User
    {
        return \App\Models\User::where('role', 'manutentore_straordinario')
            ->where('active', true)->orderBy('id')->first();
    }

    /** Assegna la richiesta straordinaria al manutentore straordinario e invia l'email di apertura. */
    private function assegnaStraordinario(MaintenanceRequest $req): void
    {
        $straord = $this->manutentoreStraordinario();
        if (! $straord) {
            return; // resterà "da assegnare" finché non si crea il ruolo
        }
        $req->external_maintainer_id = $straord->id;
        $req->save();
        $this->inviaEmailAssegnazione($req, $straord);
        (new PushNotifier())->assegnata($req, $straord);
    }

    /** Invia (best-effort) l'email di assegnazione al manutentore. Ritorna true se inviata. */
    private function inviaEmailAssegnazione(MaintenanceRequest $req, ?\App\Models\User $maintainer): bool
    {
        if (! $maintainer || ! $maintainer->email) {
            return false;
        }
        try {
            Mail::to($maintainer->email)->send(new RichiestaEsternaAssegnata($req, $maintainer->name));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Redirect con messaggio a seconda dell'esito dell'invio email. */
    private function redirectConEmail(RedirectResponse $redirect, MaintenanceRequest $req, ?\App\Models\User $maintainer): RedirectResponse
    {
        $nome = $maintainer?->name ?? 'manutentore';

        // Notifica push al manutentore assegnato (indipendente dall'email).
        if ($maintainer) {
            (new PushNotifier())->assegnata($req, $maintainer);
        }

        if (! $maintainer || ! $maintainer->email) {
            return $redirect->with('ok', 'Richiesta assegnata a '.$nome.'.')
                ->with('warn', 'Questo manutentore non ha un\'email configurata: aggiungila in Utenti per inviare la notifica.');
        }

        if ($this->inviaEmailAssegnazione($req, $maintainer)) {
            return $redirect->with('ok', 'Richiesta assegnata a '.$nome.'. Email di notifica inviata a '.$maintainer->email.'.');
        }

        return $redirect->with('ok', 'Richiesta assegnata a '.$nome.'.')
            ->with('warn', "Assegnazione riuscita, ma l'email di notifica non è stata inviata: verifica la configurazione email del server.");
    }
}
