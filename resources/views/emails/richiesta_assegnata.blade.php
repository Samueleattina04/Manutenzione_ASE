<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background:#faf7ef; font-family:Arial,Helvetica,sans-serif; color:#1f2a37;">
    <div style="max-width:600px; margin:0 auto; padding:20px;">
        <div style="background:#fff; border:1px solid #e6e2d6; border-radius:12px; overflow:hidden;">
            <div style="height:8px; background:#d8ab1f;"></div>
            <div style="padding:24px;">
                <h2 style="margin:0 0 4px; font-size:20px;">🔧 Richiesta di manutenzione assegnata</h2>
                <p style="margin:0 0 18px; color:#5b6672;">Numero richiesta: <strong>#{{ $richiesta->id }}</strong></p>

                <p style="margin:0 0 16px;">Ciao {{ $manutentoreNome }},<br>
                    ti è stata assegnata una richiesta di <strong>manutenzione esterna</strong>. Di seguito il riepilogo.</p>

                <table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px;">
                    @php
                        $priorita = config('manutenzione.priorita.'.$richiesta->priorita.'.label', $richiesta->priorita);
                        $stato = config('manutenzione.stati.'.$richiesta->status.'.label', $richiesta->status);
                    @endphp
                    @foreach([
                        'Impianto' => $richiesta->impiantoLabel(),
                        'Macchinario' => $richiesta->macchinario,
                        'Reparto' => $richiesta->reparto ?: '—',
                        'Priorità' => $priorita,
                        'Stato' => $stato,
                        'Aperta da' => $richiesta->operatore,
                        'Aperta il' => optional($richiesta->created_at)->format('d/m/Y H:i'),
                    ] as $label => $value)
                        <tr>
                            <td style="padding:8px 10px; border-bottom:1px solid #eee; color:#5b6672; width:38%;">{{ $label }}</td>
                            <td style="padding:8px 10px; border-bottom:1px solid #eee; font-weight:bold;">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>

                @if($richiesta->descrizione)
                    <p style="margin:16px 0 4px; color:#5b6672; font-size:13px; text-transform:uppercase;">Descrizione evento</p>
                    <p style="margin:0; white-space:pre-wrap;">{{ $richiesta->descrizione }}</p>
                @endif
                @if($richiesta->note)
                    <p style="margin:16px 0 4px; color:#5b6672; font-size:13px; text-transform:uppercase;">Note</p>
                    <p style="margin:0; white-space:pre-wrap;">{{ $richiesta->note }}</p>
                @endif

                <p style="margin:22px 0 0; padding:14px; background:#f4e9c8; border-radius:8px; font-size:14px;">
                    Quando sarai in azienda, accedi all'applicativo per prendere in carico la richiesta,
                    aggiornare lo stato e registrare l'intervento (con eventuali foto).
                </p>
            </div>
        </div>
        <p style="text-align:center; color:#98917f; font-size:12px; margin:16px 0 0;">
            Manutenzione ASE · email automatica, non rispondere a questo messaggio.
        </p>
    </div>
</body>
</html>
