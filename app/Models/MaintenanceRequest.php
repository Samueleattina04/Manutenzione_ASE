<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'impianto', 'impianto_altro', 'macchinario', 'reparto', 'descrizione',
    'priorita', 'note', 'operatore', 'status', 'created_by', 'assigned_to',
    'taken_at', 'resolved_at',
])]
class MaintenanceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
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

    /**
     * Chi può eliminare la richiesta:
     * - un amministratore, sempre;
     * - l'operatore che l'ha creata, solo finché è ancora "aperta"
     *   (cioè prima che un manutentore la prenda in carico).
     */
    public function deletableBy(User $user): bool
    {
        return $user->isAdmin()
            || ($this->created_by === $user->id && $this->status === 'aperta');
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

    /** Etichetta leggibile per l'impianto (gestisce "Altro"). */
    public function impiantoLabel(): string
    {
        if ($this->impianto === 'Altro' && $this->impianto_altro) {
            return 'Altro: '.$this->impianto_altro;
        }

        return $this->impianto;
    }
}
