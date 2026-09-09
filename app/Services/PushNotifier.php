<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Collection;

/**
 * Invio delle notifiche push (in tempo reale) legate alle richieste.
 * Se le chiavi VAPID non sono configurate o non ci sono iscrizioni, non fa nulla.
 */
class PushNotifier
{
    /** Nuova richiesta interna → avvisa tutti i manutentori interni. */
    public function nuovaRichiestaInterna(MaintenanceRequest $r): void
    {
        $ids = User::where('role', 'manutentore')->where('active', true)->pluck('id')->all();
        if (! $ids) {
            return;
        }

        $this->inviaAUtenti($ids, [
            'title' => 'Nuova richiesta interna #'.$r->id,
            'body' => trim($r->impiantoLabel().' · '.$r->macchinario),
            'url' => $this->url($r),
            'tag' => 'richiesta-'.$r->id,
        ]);
    }

    /** Richiesta assegnata a un manutentore (esterno/straordinario) → avvisa lui. */
    public function assegnata(MaintenanceRequest $r, User $manutentore): void
    {
        $this->inviaAUtenti([$manutentore->id], [
            'title' => 'Richiesta #'.$r->id.' assegnata a te',
            'body' => trim($r->destinatarioLabel().' · '.$r->macchinario),
            'url' => $this->url($r),
            'tag' => 'richiesta-'.$r->id,
        ]);
    }

    /** Cambio di stato / nuovo intervento → avvisa gli operatori del reparto d'accesso. */
    public function statoAggiornato(MaintenanceRequest $r): void
    {
        $stato = config('manutenzione.stati.'.$r->status.'.label', $r->status);
        $this->inviaAReparto($r->reparto_accesso, [
            'title' => 'Aggiornamento richiesta #'.$r->id,
            'body' => $r->macchinario.' · '.$stato,
            'url' => $this->url($r),
            'tag' => 'richiesta-'.$r->id,
        ]);
    }

    /** Tempo di intervento indicato dal manutentore → avvisa gli operatori del reparto. */
    public function tempoIntervento(MaintenanceRequest $r): void
    {
        if (! $r->etaLabel()) {
            return;
        }
        $this->inviaAReparto($r->reparto_accesso, [
            'title' => 'Intervento previsto · richiesta #'.$r->id,
            'body' => 'Il manutentore sarà in reparto '.$r->etaLabel(),
            'url' => $this->url($r),
            'tag' => 'richiesta-'.$r->id,
        ]);
    }

    // --- invio ---------------------------------------------------------------

    private function inviaAUtenti(array $userIds, array $payload): void
    {
        if (! WebPush::enabled() || ! $userIds) {
            return;
        }
        $this->dispatch(PushSubscription::whereIn('user_id', $userIds)->get(), $payload);
    }

    private function inviaAReparto(?string $reparto, array $payload): void
    {
        if (! WebPush::enabled() || ! $reparto) {
            return;
        }
        $this->dispatch(PushSubscription::where('reparto', $reparto)->get(), $payload);
    }

    private function dispatch(Collection $subs, array $payload): void
    {
        if ($subs->isEmpty()) {
            return;
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subs as $sub) {
            $status = WebPush::send($sub->endpoint, $sub->p256dh, $sub->auth, $json);
            // 404/410: la subscription non esiste più → la rimuoviamo.
            if (in_array($status, [404, 410], true)) {
                $sub->delete();
            }
        }
    }

    private function url(MaintenanceRequest $r): string
    {
        return route('richieste.show', $r);
    }
}
