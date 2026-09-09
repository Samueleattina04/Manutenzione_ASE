@extends('layouts.app')
@section('title', 'Richiesta #'.$req->id)

@section('content')
@php($me = auth()->user())

<div class="detail-head">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('richieste.index') }}" class="back" title="Indietro">←</a>
    <h2 style="margin:0; font-size:18px">Richiesta #{{ $req->id }}</h2>
</div>

@if($errors->any())
    <div class="inline-error">{{ $errors->first() }}</div>
@endif

<div class="card">
    <div data-poll="dettaglio" data-poll-url="{{ route('richieste.cronologia', $req) }}">
        @include('requests.partials.detail_body')
    </div>
</div>

{{-- Admin: destinatario e assegnazione del manutentore --}}
@if($me->isAdmin())
    <div class="action-card">
        <div class="block-title" style="margin-top:0">🛠️ Destinatario e assegnazione</div>
        <form method="POST" action="{{ route('richieste.assegnazione', $req) }}" data-guard>
            @csrf
            <div class="field">
                <label>Tipo di manutenzione</label>
                <select name="destinatario" data-dest-select>
                    @foreach(config('manutenzione.destinatari') as $val => $label)
                        <option value="{{ $val }}" @selected($req->destinatario === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field mb0" data-esterno-field style="display:{{ $req->destinatario === 'esterna' ? 'block' : 'none' }}">
                <label>Manutentore esterno</label>
                @if($manutentoriEsterni->isEmpty())
                    <div class="muted" style="font-size:13px">
                        Nessun manutentore esterno configurato. Crealo in
                        <a href="{{ route('utenti.index') }}">Utenti</a> (ruolo “Manutentore esterno”).
                    </div>
                @else
                    <select name="external_maintainer_id">
                        <option value="">Scegli il manutentore esterno…</option>
                        @foreach($manutentoriEsterni as $mx)
                            <option value="{{ $mx->id }}" @selected($req->external_maintainer_id === $mx->id)>{{ $mx->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="hint" data-straord-hint style="display:{{ $req->destinatario === 'straordinaria' ? 'block' : 'none' }}">
                Verrà assegnata automaticamente al <strong>manutentore straordinario</strong>, che riceve subito l’email.
            </div>
            <div class="hint" data-interna-hint style="display:{{ $req->destinatario === 'interna' ? 'block' : 'none' }}">
                La manutenzione interna è visibile a <strong>tutti i manutentori interni</strong>, senza assegnazione.
            </div>

            <button type="submit" class="btn btn-primary mt8">Aggiorna</button>
        </form>

        @if($req->richiedeAssegnazione())
            <div class="dl" style="margin-top:14px">
                <dt>{{ $req->manutentoreRuoloLabel() }}</dt>
                <dd>{{ $req->externalMaintainer?->name ?? '⚠️ Da assegnare' }}</dd>
            </div>
            @if($req->daAssegnare())
                <div class="field-error mt8">Questa richiesta non è ancora stata assegnata a un manutentore.</div>
            @endif
        @endif
    </div>

    <script>
    (function () {
        var sel = document.querySelector('[data-dest-select]');
        if (!sel) return;
        function upd() {
            var v = sel.value;
            var e = document.querySelector('[data-esterno-field]');
            var s = document.querySelector('[data-straord-hint]');
            var i = document.querySelector('[data-interna-hint]');
            if (e) e.style.display = (v === 'esterna') ? 'block' : 'none';
            if (s) s.style.display = (v === 'straordinaria') ? 'block' : 'none';
            if (i) i.style.display = (v === 'interna') ? 'block' : 'none';
        }
        sel.addEventListener('change', upd);
        upd();
    })();
    </script>
@endif

{{-- Operatore/admin: aggiungi foto del problema (finché la richiesta è aperta) --}}
@if(($req->created_by === $me->id || $me->isAdmin()) && ! $req->isDone())
    <div class="action-card">
        <div class="block-title" style="margin-top:0">Aggiungi foto del problema</div>
        <form method="POST" action="{{ route('richieste.foto', $req) }}" enctype="multipart/form-data" data-guard>
            @csrf
            <x-photo-uploader hint="Scatta la foto del problema o scegli dalla galleria" />
            <button type="submit" class="btn btn-ghost btn-sm mt8">Carica foto</button>
        </form>
    </div>
@endif

{{-- Manutentore/admin: tempo di intervento previsto (visibile all'operatore) --}}
@if($me->canManutentore() && ! $req->isDone())
    <div class="action-card">
        <div class="block-title" style="margin-top:0">🕒 Tempo di intervento</div>
        <p class="muted" style="margin:-4px 0 10px; font-size:13px">
            Indica entro quanto tempo sarai in reparto per la sistemazione: l’operatore potrà vederlo.
        </p>
        @if($req->eta_intervento)
            <div class="hint" style="margin-bottom:10px">
                Attualmente previsto: <strong>{{ $req->etaLabel() ?? $req->eta_intervento->format('d/m/Y H:i') }}</strong>
            </div>
        @endif
        <form method="POST" action="{{ route('richieste.eta', $req) }}" data-guard
              style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end">
            @csrf
            <div class="field mb0" style="flex:1; min-width:200px">
                <label>Sarò in reparto</label>
                <select name="eta" required>
                    <option value="" disabled selected>Scegli entro quanto…</option>
                    @foreach(config('manutenzione.eta_opzioni') as $min => $label)
                        <option value="{{ $min }}">{{ $label }}</option>
                    @endforeach
                    @if($req->eta_intervento)
                        <option value="annulla">Rimuovi il tempo previsto</option>
                    @endif
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Salva</button>
        </form>
    </div>
@endif

{{-- Manutentore/admin: pannello di aggiornamento --}}
@if($me->canManutentore() && $req->status !== 'chiusa')
    <div class="action-card">
        <div class="block-title" style="margin-top:0">Aggiorna la richiesta</div>

        @if(! $req->taken_at)
            <form method="POST" action="{{ route('richieste.aggiorna', $req) }}" data-guard style="margin-bottom:12px">
                @csrf
                <input type="hidden" name="status" value="presa_in_carico">
                <button type="submit" class="btn btn-ghost btn-block">🙋 Prendi in carico</button>
            </form>
        @endif

        <form method="POST" action="{{ route('richieste.aggiorna', $req) }}" enctype="multipart/form-data" data-guard>
            @csrf
            <div class="field mb0">
                <label>Nuovo stato</label>
                <div class="status-picker">
                    @foreach(config('manutenzione.stati_manutentore') as $val)
                        {{-- "Presa in carico" è gestita dal pulsante dedicato qui sopra --}}
                        @continue($val === 'presa_in_carico')
                        @php($s = config('manutenzione.stati.'.$val))
                        <label class="status-opt">
                            <input type="radio" name="status" value="{{ $val }}">
                            <span class="dot" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="field" style="margin-top:14px">
                <label>Descrizione intervento</label>
                <textarea name="note" placeholder="Descrivi l’intervento eseguito (cosa è stato fatto, ricambi, ecc.)">{{ old('note') }}</textarea>
            </div>

            <div class="field">
                <label>Foto soluzione</label>
                <x-photo-uploader hint="Scatta la foto della soluzione o scegli dalla galleria" />
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">Salva aggiornamento</button>
        </form>
    </div>
@endif

{{-- Eliminazione richiesta (solo amministratore) --}}
@if($req->deletableBy($me))
    <div style="margin-top:18px; text-align:right">
        <form method="POST" action="{{ route('richieste.destroy', $req) }}"
              data-confirm="Eliminare definitivamente la richiesta #{{ $req->id }}? L'operazione non è reversibile.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">🗑 Elimina richiesta</button>
        </form>
    </div>
@endif
@endsection
