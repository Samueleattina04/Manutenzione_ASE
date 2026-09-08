<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background:#faf7ef; font-family:Arial,Helvetica,sans-serif; color:#1f2a37;">
    <div style="max-width:680px; margin:0 auto; padding:20px;">
        <div style="background:#fff; border:1px solid #e6e2d6; border-radius:12px; overflow:hidden;">
            <div style="height:8px; background:#d8ab1f;"></div>
            <div style="padding:24px;">
                <h2 style="margin:0 0 4px; font-size:20px;">🔧 Riepilogo richieste aperte</h2>
                <p style="margin:0 0 18px; color:#5b6672;">{{ $dataOggi }}</p>

                <p style="margin:0 0 16px;">Ciao {{ $destinatario->name }},<br>
                    @if($isAdmin)
                        di seguito <strong>tutte</strong> le richieste di manutenzione ancora aperte
                        ({{ $richieste->count() }}). Il dettaglio completo è nel file Excel allegato.
                    @else
                        di seguito le richieste di manutenzione aperte <strong>assegnate a te</strong>
                        ({{ $richieste->count() }}). Il dettaglio completo è nel file Excel allegato.
                    @endif
                </p>

                <table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:13px;">
                    <tr style="background:#f4efe1;">
                        <th style="padding:8px 8px; text-align:left; border-bottom:1px solid #e6e2d6;">#</th>
                        <th style="padding:8px 8px; text-align:left; border-bottom:1px solid #e6e2d6;">Priorità</th>
                        <th style="padding:8px 8px; text-align:left; border-bottom:1px solid #e6e2d6;">Macchinario</th>
                        <th style="padding:8px 8px; text-align:left; border-bottom:1px solid #e6e2d6;">Reparto</th>
                        <th style="padding:8px 8px; text-align:left; border-bottom:1px solid #e6e2d6;">Stato</th>
                        <th style="padding:8px 8px; text-align:left; border-bottom:1px solid #e6e2d6;">Aperta il</th>
                    </tr>
                    @foreach($richieste as $r)
                        @php
                            $pShort = config('manutenzione.priorita.'.$r->priorita.'.short', $r->priorita);
                            $pColor = config('manutenzione.priorita.'.$r->priorita.'.color', '#78909c');
                            $stato = config('manutenzione.stati.'.$r->status.'.label', $r->status);
                        @endphp
                        <tr>
                            <td style="padding:8px 8px; border-bottom:1px solid #f0ece0; font-weight:bold;">{{ $r->id }}</td>
                            <td style="padding:8px 8px; border-bottom:1px solid #f0ece0;">
                                <span style="display:inline-block; padding:2px 8px; border-radius:10px; color:#fff; font-size:12px; background:{{ $pColor }}">{{ $pShort }}</span>
                            </td>
                            <td style="padding:8px 8px; border-bottom:1px solid #f0ece0;">
                                {{ $r->macchinario }}
                                <div style="color:#8a8570; font-size:11px;">{{ $r->impiantoLabel() }}</div>
                            </td>
                            <td style="padding:8px 8px; border-bottom:1px solid #f0ece0;">{{ $r->reparto ?: '—' }}</td>
                            <td style="padding:8px 8px; border-bottom:1px solid #f0ece0;">{{ $stato }}</td>
                            <td style="padding:8px 8px; border-bottom:1px solid #f0ece0;">{{ optional($r->created_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </table>

                <p style="margin:22px 0 0; padding:14px; background:#f4e9c8; border-radius:8px; font-size:14px;">
                    📎 In allegato il file <strong>Excel</strong> con l'elenco completo e tutti i dettagli
                    (descrizione, note, interventi, foto registrate, tempi).
                </p>
            </div>
        </div>
        <p style="text-align:center; color:#98917f; font-size:12px; margin:16px 0 0;">
            {{ config('manutenzione.azienda') }} · email automatica, non rispondere a questo messaggio.
        </p>
    </div>
</body>
</html>
