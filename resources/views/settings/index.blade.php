@extends('layouts.app')
@section('title', 'Impostazioni')

@section('content')
<h2 style="margin:0 0 4px">Impostazioni</h2>
<p class="muted" style="margin-top:0">Gestisci gli elenchi usati nel modulo delle richieste.</p>

@if($errors->any())
    <div class="inline-error">{{ $errors->first() }}</div>
@endif

<div style="display:grid; gap:18px; grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
    @foreach([['Impianti', 'impianto', $impianti], ['Reparti', 'reparto', $reparti]] as [$titolo, $type, $items])
        <div class="card">
            <h3 style="margin:0 0 12px">{{ $titolo }}</h3>

            <div class="setting-list">
                @forelse($items as $item)
                    <div class="setting-row">
                        <span class="setting-val">{{ $item->value }}</span>
                        <div class="setting-actions">
                            @unless($loop->first)
                                <form method="POST" action="{{ route('impostazioni.voce.move', $item) }}">
                                    @csrf<input type="hidden" name="dir" value="up">
                                    <button class="iconbtn-sm" title="Sposta su">↑</button>
                                </form>
                            @endunless
                            @unless($loop->last)
                                <form method="POST" action="{{ route('impostazioni.voce.move', $item) }}">
                                    @csrf<input type="hidden" name="dir" value="down">
                                    <button class="iconbtn-sm" title="Sposta giù">↓</button>
                                </form>
                            @endunless
                            <button class="btn btn-ghost btn-sm" type="button" data-modal-open="edit-{{ $item->id }}">Modifica</button>
                            <form method="POST" action="{{ route('impostazioni.voce.destroy', $item) }}"
                                  data-confirm="Eliminare «{{ $item->value }}» dall'elenco {{ $titolo }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" type="submit">Elimina</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="muted" style="font-size:14px">Nessuna voce.</div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('impostazioni.voce.store') }}" data-guard
                  style="display:flex; gap:8px; margin-top:14px">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="text" name="value" placeholder="Aggiungi {{ \Illuminate\Support\Str::lower($titolo) }}…" required>
                <button type="submit" class="btn btn-primary">Aggiungi</button>
            </form>
        </div>

        {{-- Modali di modifica per questo elenco --}}
        @foreach($items as $item)
            <div class="modal-overlay" id="edit-{{ $item->id }}" data-modal hidden>
                <div class="modal">
                    <h3>Modifica {{ \Illuminate\Support\Str::lower($titolo) }}</h3>
                    <form method="POST" action="{{ route('impostazioni.voce.update', $item) }}" data-guard>
                        @csrf @method('PUT')
                        <div class="field">
                            <label>Valore</label>
                            <input type="text" name="value" value="{{ $item->value }}" required>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-ghost" data-modal-close>Annulla</button>
                            <button type="submit" class="btn btn-primary">Salva</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endforeach
</div>
@endsection
