@extends('layouts.app')
@section('title', 'Richieste di manutenzione')

@section('content')
    @php
        $priorita = config('manutenzione.priorita');
        $impianti = \App\Support\Lists::impianti();
        $reparti = \App\Support\Lists::reparti();
        $destinatari = config('manutenzione.destinatari');
        $u = auth()->user();
        // Il tipo di manutenzione ha senso solo per chi vede più tipi (admin e operatore);
        // i manutentori vedono già solo il proprio tipo.
        $mostraTipo = $u->isAdmin() || $u->isOperatore();

        // Riepilogo dei filtri attivi, come chip rimovibili.
        $chips = [];
        if ($filters['status'] !== 'attive') {
            $lblStato = ['tutte' => 'Tutte', 'chiuse' => 'Chiuse'][$filters['status']] ?? ucfirst($filters['status']);
            $chips[] = ['l' => 'Stato: '.$lblStato, 'u' => request()->fullUrlWithQuery(['status' => null])];
        }
        if ($filters['priorita'] !== '') {
            $chips[] = ['l' => 'Priorità: '.($priorita[$filters['priorita']]['short'] ?? $filters['priorita']), 'u' => request()->fullUrlWithQuery(['priorita' => null])];
        }
        if ($mostraTipo && $filters['destinatario'] !== '') {
            $chips[] = ['l' => $destinatari[$filters['destinatario']] ?? $filters['destinatario'], 'u' => request()->fullUrlWithQuery(['destinatario' => null])];
        }
        if ($filters['reparto'] !== '') {
            $chips[] = ['l' => 'Reparto: '.$filters['reparto'], 'u' => request()->fullUrlWithQuery(['reparto' => null])];
        }
        if ($filters['impianto'] !== '') {
            $chips[] = ['l' => 'Impianto: '.$filters['impianto'], 'u' => request()->fullUrlWithQuery(['impianto' => null])];
        }
        if ($filters['dal'] !== '') {
            $chips[] = ['l' => 'Dal '.$filters['dal'], 'u' => request()->fullUrlWithQuery(['dal' => null])];
        }
        if ($filters['al'] !== '') {
            $chips[] = ['l' => 'Al '.$filters['al'], 'u' => request()->fullUrlWithQuery(['al' => null])];
        }
        if ($filters['mine']) {
            $chips[] = ['l' => 'Le mie', 'u' => request()->fullUrlWithQuery(['mine' => null])];
        }
        if ($filters['da_assegnare']) {
            $chips[] = ['l' => 'Da assegnare', 'u' => request()->fullUrlWithQuery(['da_assegnare' => null])];
        }
        $nFiltri = count($chips); // filtri attivi (escluso il testo di ricerca)
    @endphp

    <form method="GET" action="{{ route('richieste.index') }}">
        {{-- Barra: ricerca + bottone Filtri --}}
        <div class="toolbar">
            <input type="search" name="q" class="search" value="{{ $filters['q'] }}" style="flex:1; min-width:160px"
                   placeholder="🔎 Cerca n° ticket (#45), macchinario, reparto…">
            <button type="submit" class="btn btn-ghost btn-sm">Cerca</button>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-open="filtri-modal">
                ⚙️ Filtri
                @if($nFiltri)<span class="filter-count">{{ $nFiltri }}</span>@endif
            </button>
            @unless($u->isOperatore())
                <a href="{{ route('richieste.export', request()->query()) }}" class="btn btn-ghost btn-sm">⬇️ Excel</a>
            @endunless
        </div>

        {{-- Riepilogo filtri attivi --}}
        @if($nFiltri || $filters['q'] !== '')
            <div class="toolbar" style="margin-top:-6px; gap:6px">
                @if($filters['q'] !== '')
                    <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="chip active">Ricerca: “{{ \Illuminate\Support\Str::limit($filters['q'], 24) }}” ✕</a>
                @endif
                @foreach($chips as $c)
                    <a href="{{ $c['u'] }}" class="chip active">{{ $c['l'] }} ✕</a>
                @endforeach
                <a href="{{ route('richieste.index') }}" class="chip">Azzera tutto</a>
            </div>
        @endif

        {{-- Pannello filtri (modale) --}}
        <div id="filtri-modal" class="modal-overlay" data-modal hidden>
            <div class="modal">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px">
                    <h3 style="margin:0">Filtri</h3>
                    <button type="button" class="iconbtn-sm" data-modal-close aria-label="Chiudi">✕</button>
                </div>

                <div class="field">
                    <label>Stato</label>
                    <select name="status">
                        <option value="attive" @selected($filters['status'] === 'attive')>Attive</option>
                        <option value="tutte" @selected($filters['status'] === 'tutte')>Tutte</option>
                        <option value="chiuse" @selected($filters['status'] === 'chiuse')>Chiuse</option>
                    </select>
                </div>

                <div class="field">
                    <label>Priorità</label>
                    <select name="priorita">
                        <option value="">Tutte</option>
                        @foreach($priorita as $val => $p)
                            <option value="{{ $val }}" @selected($filters['priorita'] === $val)>{{ $p['short'] }}</option>
                        @endforeach
                    </select>
                </div>

                @if($mostraTipo)
                    <div class="field">
                        <label>Tipo di manutenzione</label>
                        <select name="destinatario">
                            <option value="">Tutti</option>
                            @foreach($destinatari as $val => $label)
                                <option value="{{ $val }}" @selected($filters['destinatario'] === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="field">
                    <label>Reparto</label>
                    <select name="reparto">
                        <option value="">Tutti i reparti</option>
                        @foreach($reparti as $rp)
                            <option value="{{ $rp }}" @selected($filters['reparto'] === $rp)>{{ $rp }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Impianto</label>
                    <select name="impianto">
                        <option value="">Tutti gli impianti</option>
                        @foreach($impianti as $imp)
                            <option value="{{ $imp }}" @selected($filters['impianto'] === $imp)>{{ $imp }}</option>
                        @endforeach
                    </select>
                </div>

                @unless($u->isOperatore())
                    <div class="detail-grid" style="margin:0 0 16px">
                        <div class="field mb0"><label>Dal</label><input type="date" name="dal" value="{{ $filters['dal'] }}"></div>
                        <div class="field mb0"><label>Al</label><input type="date" name="al" value="{{ $filters['al'] }}"></div>
                    </div>
                    <label class="radio-item" style="margin-bottom:10px">
                        <input type="checkbox" name="mine" value="1" @checked($filters['mine'])>
                        <span>Solo le mie richieste</span>
                    </label>
                @endunless

                @if($u->isAdmin())
                    <label class="radio-item" style="margin-bottom:10px">
                        <input type="checkbox" name="da_assegnare" value="1" @checked($filters['da_assegnare'])>
                        <span>Solo da assegnare @if($stats['da_assegnare'] > 0)({{ $stats['da_assegnare'] }})@endif</span>
                    </label>
                @endif

                <div class="modal-actions">
                    <a href="{{ route('richieste.index') }}" class="btn btn-ghost">Azzera</a>
                    <button type="submit" class="btn btn-primary">Applica filtri</button>
                </div>
            </div>
        </div>
    </form>

    <div data-poll="lista" data-poll-url="{{ route('richieste.elenco') }}">
        @include('requests.partials.list')
    </div>
@endsection
