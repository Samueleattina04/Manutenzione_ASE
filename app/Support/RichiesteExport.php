<?php

namespace App\Support;

use App\Models\MaintenanceRequest;

/**
 * Mappatura condivisa delle richieste in righe tabellari, usata sia
 * dall'export CSV manuale sia dal riepilogo giornaliero via email (Excel).
 */
class RichiesteExport
{
    /** Intestazioni delle colonne. */
    public static function headers(): array
    {
        return [
            'N.', 'Data apertura', 'Impianto', 'Macchinario', 'Reparto', 'Destinatario',
            'Priorità', 'Stato', 'Operatore', 'Manutentore', 'Manutentore esterno',
            'Presa in carico', 'Intervento previsto', 'Risolta il', 'Tempo risoluzione',
            'Descrizione evento', 'Note', 'Interventi',
        ];
    }

    /** Larghezze indicative delle colonne (per il file Excel). */
    public static function widths(): array
    {
        return [6, 16, 18, 24, 16, 22, 10, 18, 18, 18, 20, 16, 16, 16, 14, 40, 30, 50];
    }

    /** Una richiesta trasformata in riga (valori nell'ordine di headers()). */
    public static function row(MaintenanceRequest $r): array
    {
        $interventi = $r->updates->map(function ($u) {
            $stato = $u->status ? config('manutenzione.stati.'.$u->status.'.label', $u->status) : '';
            $line = trim(($u->created_at?->format('d/m/Y H:i') ?? '').' '.($u->user?->name ?? ''));
            if ($stato) {
                $line .= ' ['.$stato.']';
            }
            if ($u->note) {
                $line .= ': '.$u->note;
            }

            return $line;
        })->implode("\n");

        return [
            (string) $r->id,
            $r->created_at?->format('d/m/Y H:i'),
            $r->impiantoLabel(),
            $r->macchinario,
            $r->reparto,
            $r->destinatarioLabel(),
            config('manutenzione.priorita.'.$r->priorita.'.short', $r->priorita),
            config('manutenzione.stati.'.$r->status.'.label', $r->status),
            $r->operatore,
            $r->assignee?->name,
            $r->externalMaintainer?->name,
            $r->taken_at?->format('d/m/Y H:i'),
            $r->eta_intervento?->format('d/m/Y H:i'),
            $r->resolved_at?->format('d/m/Y H:i'),
            $r->resolutionDuration(),
            $r->descrizione,
            $r->note,
            $interventi,
        ];
    }

    /** Trasforma una collezione di richieste in un array di righe. */
    public static function rows(iterable $requests): array
    {
        $out = [];
        foreach ($requests as $r) {
            $out[] = self::row($r);
        }

        return $out;
    }
}
