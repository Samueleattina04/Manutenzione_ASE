<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'impianto', 'impianto_altro', 'macchinario', 'reparto', 'descrizione',
    'priorita', 'destinatario', 'note', 'operatore', 'status', 'created_by',
    'assigned_to', 'external_maintainer_id', 'taken_at', 'eta_intervento', 'resolved_at',
])]
class MaintenanceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'eta_intervento' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function externalMaintainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'external_maintainer_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RequestUpdate::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('created_at')->orderBy('id');
    }

    public function problemaAttachments(): HasMany
    {
        return $this->attachments()->where('kind', 'problema');
    }

    public function soluzioneAttachments(): HasMany
    {
        return $this->attachments()->where('kind', 'soluzione');
    }

    public function isDone(): bool
    {
        return (bool) (config("manutenzione.stati.{$this->status}.done") ?? false);
    }

    /** Solo l'amministratore può eliminare le richieste. */
    public function deletableBy(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Tempo trascorso tra apertura e risoluzione, in formato leggibile. */
    public function resolutionDuration(): ?string
    {
        if (! $this->resolved_at || ! $this->created_at) {
            return null;
        }
        $mins = (int) $this->created_at->diffInMinutes($this->resolved_at);
        $d = intdiv($mins, 1440);
        $mins -= $d * 1440;
        $h = intdiv($mins, 60);
        $m = $mins - $h * 60;
        $parts = [];
        if ($d) {
            $parts[] = $d.'g';
        }
        if ($h) {
            $parts[] = $h.'h';
        }
        if ($m && ! $d) {
            $parts[] = $m.'min';
        }

        return $parts ? implode(' ', $parts) : '<1min';
    }

    public function destinatarioLabel(): string
    {
        return config('manutenzione.destinatari.'.$this->destinatario, $this->destinatario);
    }

    public function isEsterna(): bool
    {
        return $this->destinatario === 'esterna';
    }

    /**
     * Destinatari che prevedono l'assegnazione di un manutentore specifico e
     * l'invio dell'email di notifica: manutenzione esterna e straordinaria.
     */
    public function richiedeAssegnazione(): bool
    {
        return in_array($this->destinatario, ['esterna', 'straordinaria'], true);
    }

    /** Solo la manutenzione esterna richiede una scelta manuale dell'admin. */
    public function assegnazioneManuale(): bool
    {
        return $this->destinatario === 'esterna';
    }

    /** Richiesta (esterna o straordinaria) in attesa di assegnazione. */
    public function daAssegnare(): bool
    {
        return $this->richiedeAssegnazione() && ! $this->external_maintainer_id;
    }

    /** Etichetta del ruolo del manutentore assegnato, in base al destinatario. */
    public function manutentoreRuoloLabel(): string
    {
        return $this->destinatario === 'straordinaria'
            ? 'Manutentore straordinario'
            : 'Manutentore esterno';
    }

    /** Retro-compatibilità: richiesta esterna in attesa di assegnazione. */
    public function esternaDaAssegnare(): bool
    {
        return $this->daAssegnare();
    }

    /**
     * Etichetta leggibile del tempo di intervento previsto dal manutentore.
     * Restituisce null se non impostato o se la richiesta è già risolta.
     */
    public function etaLabel(): ?string
    {
        if (! $this->eta_intervento || $this->isDone()) {
            return null;
        }

        $eta = $this->eta_intervento;
        $ora = $eta->format('H:i');

        if ($eta->isToday()) {
            return 'oggi alle '.$ora;
        }
        if ($eta->isTomorrow()) {
            return 'domani alle '.$ora;
        }

        return $eta->format('d/m/Y').' alle '.$ora;
    }

    /** Etichetta leggibile per l'impianto (gestisce "Altro"). */
    public function impiantoLabel(): string
    {
        if ($this->impianto === 'Altro' && $this->impianto_altro) {
            return 'Altro: '.$this->impianto_altro;
        }

        return $this->impianto;
    }
}
