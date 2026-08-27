@extends('layouts.app')
@section('title', 'Nuova richiesta di manutenzione')

@section('content')
@php
    $impianti = config('manutenzione.impianti');
    $reparti = config('manutenzione.reparti');
    $priorita = config('manutenzione.priorita');
@endphp

<div class="card form-card">
    <h2 style="margin-top:0">Nuova richiesta di manutenzione</h2>
    <p class="muted" style="margin-top:-4px">Compila il modulo. I campi con <span style="color:var(--rosso)">*</span> sono obbligatori.</p>

    @if($errors->any())
        <div class="inline-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('richieste.store') }}" enctype="multipart/form-data" data-guard>
        @csrf

        {{-- Impianto --}}
        <div class="field">
            <label>Scegli l’impianto <span class="req">*</span></label>
            <div class="radio-list">
                @foreach($impianti as $imp)
                    <label class="radio-item {{ old('impianto') === $imp ? 'checked' : '' }}">
                        <input type="radio" name="impianto" value="{{ $imp }}" @checked(old('impianto') === $imp)> {{ $imp }}
                    </label>
                @endforeach
                <label class="radio-item {{ old('impianto') === 'Altro' ? 'checked' : '' }}">
                    <input type="radio" name="impianto" value="Altro" @checked(old('impianto') === 'Altro')> Altro
                </label>
                <div data-altro-input style="display:{{ old('impianto') === 'Altro' ? 'block' : 'none' }}">
                    <input type="text" name="impianto_altro" class="radio-altro-input" value="{{ old('impianto_altro') }}" placeholder="Specifica impianto">
                </div>
            </div>
            @error('impianto')<div class="field-error">{{ $message }}</div>@enderror
            @error('impianto_altro')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Macchinario --}}
        <div class="field">
            <label>Inserisci l’impianto o macchinario in questione <span class="req">*</span></label>
            <input type="text" name="macchinario" value="{{ old('macchinario') }}" placeholder="Es. Linea 2, Impastatrice, Cella frigo…">
            @error('macchinario')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Reparto --}}
        <div class="field">
            <label>Reparto</label>
            <select name="reparto">
                <option value="">Scegli (facoltativo)</option>
                @foreach($reparti as $rp)
                    <option value="{{ $rp }}" @selected(old('reparto') === $rp)>{{ $rp }}</option>
                @endforeach
            </select>
        </div>

        {{-- Descrizione --}}
        <div class="field">
            <label>Descrizione evento</label>
            <textarea name="descrizione" placeholder="Cosa è successo? Descrivi il problema riscontrato.">{{ old('descrizione') }}</textarea>
        </div>

        {{-- Priorità --}}
        <div class="field">
            <label>Livello Priorità</label>
            <div class="priority-select">
                @foreach($priorita as $val => $p)
                    @php($sel = old('priorita', 'verde') === $val)
                    <label class="priority-opt {{ $sel ? 'checked' : '' }}" style="color: {{ $p['color'] }}">
                        <input type="radio" name="priorita" value="{{ $val }}" @checked($sel)>
                        <span class="pdot" style="background: {{ $p['color'] }}"></span>
                        <span style="color:var(--ink)">{{ $p['label'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Note --}}
        <div class="field">
            <label>Note</label>
            <textarea name="note" placeholder="Note aggiuntive (facoltativo)">{{ old('note') }}</textarea>
        </div>

        {{-- Operatore --}}
        <div class="field">
            <label>Operatore <span class="req">*</span></label>
            <input type="text" name="operatore" value="{{ old('operatore', auth()->user()->name) }}" placeholder="Il tuo nome">
            @error('operatore')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        {{-- Foto --}}
        <div class="field">
            <label>Foto del problema</label>
            <div class="uploader" data-uploader>
                <div>📷 <label>Aggiungi foto del problema<input type="file" name="foto[]" accept="image/*" multiple style="display:none"></label></div>
                <div class="hint">Scatta una foto o scegli dalla galleria</div>
                <div class="thumbs" data-thumbs></div>
            </div>
            @error('foto.*')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">Invia richiesta</button>
    </form>
</div>
@endsection
