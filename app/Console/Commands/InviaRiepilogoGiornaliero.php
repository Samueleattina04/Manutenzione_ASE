<?php

namespace App\Console\Commands;

use App\Mail\RiepilogoGiornaliero;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\RichiesteExport;
use App\Support\SimpleXlsx;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class InviaRiepilogoGiornaliero extends Command
{
    protected $signature = 'richieste:riepilogo
                            {--dry : Non invia le email, mostra solo cosa verrebbe inviato}';

    protected $description = 'Invia ad amministratori e manutentori il riepilogo delle richieste aperte (con allegato Excel).';

    /** Stati considerati "aperti" (ancora da lavorare). */
    private const STATI_CHIUSI = ['risolta', 'chiusa'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $dataOggi = now()->format('d/m/Y');
        $stamp = now()->format('Y-m-d');

        // Destinatari: admin e manutentori (interni/esterni/straordinari) attivi con un'email.
        $destinatari = User::whereIn('role', ['admin', 'manutentore', 'manutentore_esterno', 'manutentore_straordinario'])
            ->where('active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('role')
            ->get();

        $inviate = 0;
        $saltate = 0;

        foreach ($destinatari as $user) {
            $richieste = $this->richiestePer($user)->get();

            if ($richieste->isEmpty()) {
                $saltate++;
                $this->line("· {$user->name} ({$user->role}): nessuna richiesta aperta, salto.");
                continue;
            }

            $rows = RichiesteExport::rows($richieste);
            $content = SimpleXlsx::build(RichiesteExport::headers(), $rows, RichiesteExport::widths());
            $fileName = 'richieste_aperte_'.$stamp.'.xlsx';

            $mailable = new RiepilogoGiornaliero(
                destinatario: $user,
                richieste: $richieste,
                isAdmin: $user->isAdmin(),
                dataOggi: $dataOggi,
                fileContent: $content,
                fileName: $fileName,
                fileMime: SimpleXlsx::MIME,
            );

            if ($dry) {
                $this->line("· [DRY] {$user->name} <{$user->email}>: {$richieste->count()} richieste (allegato ".strlen($content)." byte).");
                $inviate++;
                continue;
            }

            try {
                Mail::to($user->email)->send($mailable);
                $inviate++;
                $this->info("✓ {$user->name} <{$user->email}>: {$richieste->count()} richieste.");
            } catch (\Throwable $e) {
                report($e);
                $saltate++;
                $this->error("✗ {$user->name} <{$user->email}>: invio fallito ({$e->getMessage()}).");
            }
        }

        $this->newLine();
        $this->info("Riepilogo completato: {$inviate} email".($dry ? ' (dry-run)' : ' inviate').", {$saltate} saltate.");

        return self::SUCCESS;
    }

    /**
     * Richieste aperte visibili al destinatario:
     * - admin: tutte;
     * - manutentore interno: tutte le richieste di manutenzione interna;
     * - manutentore esterno: le esterne assegnate a lui;
     * - manutentore straordinario: le richieste di manutenzione straordinaria.
     */
    private function richiestePer(User $user): Builder
    {
        $q = MaintenanceRequest::query()
            ->whereNotIn('status', self::STATI_CHIUSI)
            ->with(['assignee', 'externalMaintainer', 'updates.user']);

        if ($user->isAdmin()) {
            // tutte
        } elseif ($user->isManutentoreEsterno()) {
            $q->where('destinatario', 'esterna')
                ->where('external_maintainer_id', $user->id);
        } elseif ($user->isManutentoreStraordinario()) {
            $q->where('destinatario', 'straordinaria');
        } else {
            // manutentore interno: tutte le richieste di manutenzione interna
            $q->where('destinatario', 'interna');
        }

        return $q
            ->orderByRaw("FIELD(priorita,'rosso','giallo','verde') asc")
            ->orderByDesc('created_at');
    }
}
