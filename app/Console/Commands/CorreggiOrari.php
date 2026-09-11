<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Correzione UNA TANTUM degli orari storici: le richieste create prima del
 * passaggio al fuso Europe/Rome erano salvate in UTC (mostrate 1-2 ore indietro).
 * Questo comando le riporta all'ora italiana corretta (con gestione ora
 * legale/solare per ogni singola data).
 *
 * Da eseguire UNA SOLA VOLTA, subito dopo aver attivato il fuso Europe/Rome,
 * meglio se in un momento senza nuove richieste in arrivo. Fare prima un backup.
 */
class CorreggiOrari extends Command
{
    protected $signature = 'orari:correggi
                            {--applica : Applica davvero le modifiche (senza, è solo una prova)}
                            {--forza : Esegui anche se risulta già applicato}';

    protected $description = 'Corregge una tantum gli orari storici salvati in UTC portandoli a Europe/Rome.';

    /** Tabelle e colonne data/ora da correggere. */
    private array $mappa = [
        'maintenance_requests' => ['created_at', 'updated_at', 'taken_at', 'eta_intervento', 'resolved_at'],
        'request_updates' => ['created_at', 'updated_at'],
        'attachments' => ['created_at', 'updated_at'],
    ];

    public function handle(): int
    {
        $applica = (bool) $this->option('applica');

        if ($applica && Cache::get('orari_utc_corretti') && ! $this->option('forza')) {
            $this->error('Correzione già applicata in precedenza. Non rieseguire (rischio doppio spostamento). Usa --forza solo se sei davvero sicuro.');

            return self::FAILURE;
        }

        $totale = 0;
        $esempi = [];

        foreach ($this->mappa as $tabella => $colonne) {
            $righe = DB::table($tabella)->get(array_merge(['id'], $colonne));
            foreach ($righe as $r) {
                $update = [];
                foreach ($colonne as $c) {
                    if (empty($r->$c)) {
                        continue;
                    }
                    $nuovo = Carbon::parse($r->$c, 'UTC')->setTimezone('Europe/Rome')->format('Y-m-d H:i:s');
                    if ($nuovo !== $r->$c) {
                        $update[$c] = $nuovo;
                    }
                }
                if ($update) {
                    $totale++;
                    if (count($esempi) < 6 && isset($update['created_at'])) {
                        $esempi[] = "  {$tabella} #{$r->id}: {$r->created_at}  ->  {$update['created_at']}";
                    }
                    if ($applica) {
                        DB::table($tabella)->where('id', $r->id)->update($update);
                    }
                }
            }
        }

        foreach ($esempi as $e) {
            $this->line($e);
        }

        if ($applica) {
            Cache::forever('orari_utc_corretti', now()->toDateTimeString());
            $this->info("Fatto: corrette {$totale} righe (orari portati a Europe/Rome).");
        } else {
            $this->warn("PROVA (nessuna modifica): verrebbero corrette {$totale} righe.");
            $this->warn('Per applicare davvero: fai un BACKUP del database, poi esegui  php artisan orari:correggi --applica');
        }

        return self::SUCCESS;
    }
}
