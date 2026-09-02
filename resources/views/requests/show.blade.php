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

{{-- Admin: assegnazione del manutentore esterno (richieste "manutenzione esterna") --}}
@if($me->isAdmin() && $req->isEsterna())
    <div class="action-card">
        <div class="block-title" style="margin-top:0">🛠️ Manutentore esterno</div>
        @if($manutentoriEsterni->isEmpty())
            <div class="muted" style="font-size:14px">
                Nessun manutentore esterno configurato. Creane uno dalla sezione
                <a href="{{ route('utenti.index') }}">Utenti</a> (ruolo “Manutentore esterno”).
            </div>
        @else
            <form method="POST" action="{{ route('richieste.assegna-esterno', $req) }}" data-guard
                  style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end">
                @csrf
                <div class="field mb0" style="flex:1; min-width:200px">
                    <label>Assegna a</label>
                    <select name="external_maintainer_id" required>
                        <option value="" disabled @selected(! $req->external_maintainer_id)>Scegli il manutentore esterno…</option>
                        @foreach($manutentoriEsterni as $mx)
                            <option value="{{ $mx->id }}" @selected($req->external_maintainer_id === $mx->id)>{{ $mx->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">{{ $req->external_maintainer_id ? 'Riassegna' : 'Assegna' }}</button>
            </form>
            @if($req->esternaDaAssegnare())
                <div class="field-error mt8">Questa richiesta esterna non è ancora stata assegnata a un manutentore.</div>
            @endif
        @endif
    </div>
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
