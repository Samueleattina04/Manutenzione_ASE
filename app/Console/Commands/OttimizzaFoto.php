<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Support\ImageOptimizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ricomprime e ridimensiona le foto già caricate in precedenza, per liberare
 * spazio su disco. Usa la stessa logica dell'ottimizzazione automatica in fase
 * di upload (vedi App\Support\ImageOptimizer e config/immagini.php).
 *
 * È sicuro da rilanciare più volte: le foto già ottimizzate vengono saltate, e
 * un file viene sostituito solo se la nuova versione è davvero più piccola.
 */
class OttimizzaFoto extends Command
{
    protected $signature = 'foto:ottimizza
                            {--dry-run : Mostra cosa verrebbe fatto, senza modificare nulla}
                            {--force : Riottimizza anche le foto che risultano già ottimizzate}';

    protected $description = 'Ricomprime le foto già caricate (ridimensiona + WebP) per liberare spazio su disco.';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $max = (int) config('immagini.max_lato', 1600);
        $formato = (string) config('immagini.formato', 'webp');

        $totale = Attachment::count();
        if ($totale === 0) {
            $this->info('Nessuna foto da elaborare.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[SIMULAZIONE] ' : '')."Elaborazione di {$totale} foto (lato max {$max}px, formato {$formato})...");
        $bar = $this->output->createProgressBar($totale);
        $bar->start();

        $ottimizzate = 0;
        $saltate = 0;
        $errori = 0;
        $mancanti = 0;
        $primaTot = 0;
        $dopoTot = 0;

        Attachment::query()->orderBy('id')->chunkById(100, function ($chunk) use (
            &$ottimizzate, &$saltate, &$errori, &$mancanti, &$primaTot, &$dopoTot,
            $disk, $dryRun, $force, $max, $formato, $bar
        ) {
            foreach ($chunk as $att) {
                $bar->advance();

                if (! $disk->exists($att->path)) {
                    $mancanti++;

                    continue;
                }

                $abs = $disk->path($att->path);
                $sizePrima = @filesize($abs) ?: (int) $att->size;

                // Già ottimizzata (formato giusto e dentro il lato massimo): salta.
                if (! $force && self::giaOttimizzata($abs, $max, $formato)) {
                    $saltate++;
                    $primaTot += $sizePrima;
                    $dopoTot += $sizePrima;

                    continue;
                }

                $res = ImageOptimizer::optimizeFile($abs, $att->mime_type);

                // Non gestibile (es. HEIC) oppure nessun guadagno: lascia il file com'è.
                if (! $res || strlen($res['contents']) >= $sizePrima) {
                    $saltate++;
                    $primaTot += $sizePrima;
                    $dopoTot += $sizePrima;

                    continue;
                }

                $sizeDopo = strlen($res['contents']);
                $primaTot += $sizePrima;
                $dopoTot += $sizeDopo;

                if ($dryRun) {
                    $ottimizzate++;

                    continue;
                }

                try {
                    $vecchioPath = $att->path;
                    $nuovoPath = 'uploads/'.Str::uuid()->toString().'.'.$res['extension'];

                    $disk->put($nuovoPath, $res['contents']);
                    $att->update([
                        'path' => $nuovoPath,
                        'mime_type' => $res['mime'],
                        'size' => $sizeDopo,
                        'original_name' => pathinfo($att->original_name ?: basename($vecchioPath), PATHINFO_FILENAME).'.'.$res['extension'],
                    ]);

                    if ($vecchioPath !== $nuovoPath) {
                        $disk->delete($vecchioPath);
                    }

                    $ottimizzate++;
                } catch (\Throwable $e) {
                    $errori++;
                    $this->newLine();
                    $this->warn("Errore sulla foto #{$att->id}: ".$e->getMessage());
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $risparmio = max(0, $primaTot - $dopoTot);
        $perc = $primaTot > 0 ? ' ('.round($risparmio / $primaTot * 100).'%)' : '';

        $this->info($dryRun ? 'Simulazione completata.' : 'Fatto.');
        $this->line('  Ottimizzate: '.$ottimizzate);
        $this->line('  Saltate (già ok / nessun guadagno): '.$saltate);
        if ($mancanti) {
            $this->line('  File mancanti su disco: '.$mancanti);
        }
        if ($errori) {
            $this->line('  Errori: '.$errori);
        }
        $this->line('  Spazio occupato prima: '.self::umano($primaTot));
        $this->line('  Spazio occupato dopo:  '.self::umano($dopoTot));
        $this->line(($dryRun ? '  Risparmio stimato:     ' : '  Spazio liberato:       ').self::umano($risparmio).$perc);

        if ($dryRun) {
            $this->newLine();
            $this->comment('Nessuna modifica effettuata. Rilancia senza --dry-run per applicare.');
        }

        return self::SUCCESS;
    }

    /** Un'immagine è "già ottimizzata" se ha il formato giusto ed è dentro il lato massimo. */
    private static function giaOttimizzata(string $abs, int $max, string $formato): bool
    {
        $info = @getimagesize($abs);
        if (! $info) {
            return false;
        }

        [$w, $h] = $info;
        $entroLato = $max <= 0 || ($w <= $max && $h <= $max);

        $tipo = $info['mime'] ?? '';
        $formatoOk = $formato === 'jpeg'
            ? str_contains($tipo, 'jpeg')
            : str_contains($tipo, 'webp');

        return $entroLato && $formatoOk;
    }

    private static function umano(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
