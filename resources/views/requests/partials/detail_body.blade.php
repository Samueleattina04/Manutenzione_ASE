<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
    <x-stato-badge :value="$req->status" />
    <x-priorita-badge :value="$req->priorita" />
</div>
<h2 style="margin:12px 0 2px; font-size:20px">{{ $req->macchinario }}</h2>
<div class="muted" style="font-size:13px">{{ $req->impiantoLabel() }}@if($req->reparto) · {{ $req->reparto }}@endif</div>

<div class="detail-grid">
    <div class="dl"><dt>Operatore</dt><dd>{{ $req->operatore }}</dd></div>
    <div class="dl"><dt>Impianto</dt><dd>{{ $req->impiantoLabel() }}</dd></div>
    <div class="dl"><dt>Reparto</dt><dd>{{ $req->reparto ?: '—' }}</dd></div>
    <div class="dl"><dt>Priorità</dt><dd>{{ config('manutenzione.priorita.'.$req->priorita.'.label', $req->priorita) }}</dd></div>
    <div class="dl"><dt>Destinatario</dt><dd>{{ $req->destinatarioLabel() }}</dd></div>
    @if($req->isEsterna())
        <div class="dl"><dt>Manutentore esterno</dt>
            <dd>{{ $req->externalMaintainer?->name ?? '⚠️ Da assegnare' }}</dd>
        </div>
    @endif
    @if($req->descrizione)
        <div class="dl full"><dt>Descrizione evento</dt><dd>{{ $req->descrizione }}</dd></div>
    @endif
    @if($req->note)
        <div class="dl full"><dt>Note</dt><dd>{{ $req->note }}</dd></div>
    @endif
</div>

<div class="detail-grid timing">
    <div class="dl"><dt>Aperta il</dt><dd>{{ $req->created_at->format('d/m/Y H:i') }}</dd></div>
    <div class="dl"><dt>Presa in carico</dt><dd>{{ $req->taken_at ? $req->taken_at->format('d/m/Y H:i') : 'In attesa' }}</dd></div>
    <div class="dl"><dt>Risolta il</dt><dd>{{ $req->resolved_at ? $req->resolved_at->format('d/m/Y H:i') : '—' }}</dd></div>
    <div class="dl"><dt>Tempo di risoluzione</dt><dd>{{ $req->resolutionDuration() ?? ($req->isDone() ? '—' : 'In corso') }}</dd></div>
    @if($req->assignee)
        <div class="dl"><dt>Manutentore</dt><dd>{{ $req->assignee->name }}</dd></div>
    @endif
</div>

@php($problema = $req->attachments->where('kind', 'problema'))
<div class="block-title">📷 Foto del problema ({{ $problema->count() }})</div>
@include('requests.partials.photos', ['photos' => $problema])

<div class="block-title">🔧 Interventi e stato</div>
@include('requests.partials.timeline')

@php($soluzione = $req->attachments->where('kind', 'soluzione'))
@if($soluzione->count())
    <div class="block-title">✅ Foto delle soluzioni ({{ $soluzione->count() }})</div>
    @include('requests.partials.photos', ['photos' => $soluzione])
@endif
