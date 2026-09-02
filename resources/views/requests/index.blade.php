@extends('layouts.app')
@section('title', 'Richieste di manutenzione')

@section('content')
    @php
        $priorita = config('manutenzione.priorita');
        $impianti = \App\Support\Lists::impianti();
    @endphp

    {{-- Filtri di stato --}}
    <div class="toolbar">
        <form method="GET" action="{{ route('richieste.index') }}" class="toolbar" style="flex:1; margin:0;">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <input type="hidden" name="priorita" value="{{ $filters['priorita'] }}">
            @if($filters['mine'])<input type="hidden" name="mine" value="1">@endif
            @if($filters['da_assegnare'])<input type="hidden" name="da_assegnare" value="1">@endif
            <input type="search" name="q" class="search" value="{{ $filters['q'] }}" placeholder="🔎 Cerca macchinario, reparto, operatore…">
            <select name="impianto" onchange="this.form.submit()">
                <option value="">Tutti gli impianti</option>
                @foreach($impianti as $imp)
                    <option value="{{ $imp }}" @selected($filters['impianto'] === $imp)>{{ $imp }}</option>
                @endforeach
            </select>
            @unless(auth()->user()->isOperatore())
                <label class="date-filter">Dal <input type="date" name="dal" value="{{ $filters['dal'] }}" onchange="this.form.submit()"></label>
                <label class="date-filter">Al <input type="date" name="al" value="{{ $filters['al'] }}" onchange="this.form.submit()"></label>
            @endunless
        </form>
        @if($filters['dal'] || $filters['al'] || $filters['q'])
            <a href="{{ request()->fullUrlWithQuery(['dal' => null, 'al' => null, 'q' => null]) }}" class="chip">✕ Azzera</a>
        @endif
        @unless(auth()->user()->isOperatore())
            <a href="{{ route('richieste.export', request()->query()) }}" class="btn btn-ghost btn-sm">⬇️ Esporta Excel</a>
        @endunless
        @if(auth()->user()->isAdmin())
            <a href="{{ $filters['da_assegnare']
                    ? request()->fullUrlWithQuery(['da_assegnare' => null])
                    : request()->fullUrlWithQuery(['da_assegnare' => 1, 'status' => 'tutte']) }}"
               class="chip {{ $filters['da_assegnare'] ? 'active' : '' }}"
               @if($filters['da_assegnare']) style="background:#c62828;border-color:#c62828;color:#fff" @endif>
               🛠️ Da assegnare @if($stats['da_assegnare'] > 0)({{ $stats['da_assegnare'] }})@endif
            </a>
        @endif
        @unless(auth()->user()->isOperatore())
            <a href="{{ request()->fullUrlWithQuery(['mine' => $filters['mine'] ? null : 1]) }}"
               class="chip {{ $filters['mine'] ? 'active' : '' }}">👤 Le mie</a>
        @endunless
    </div>

    <div class="toolbar" style="margin-top:-6px">
        <div class="chip-row">
            @foreach(['attive' => 'Attive', 'tutte' => 'Tutte', 'chiuse' => 'Chiuse'] as $val => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $val]) }}"
                   class="chip {{ $filters['status'] === $val ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="chip-row">
            @foreach($priorita as $val => $p)
                <a href="{{ request()->fullUrlWithQuery(['priorita' => $filters['priorita'] === $val ? null : $val]) }}"
                   class="chip {{ $filters['priorita'] === $val ? 'active' : '' }}"
                   @if($filters['priorita'] === $val) style="background: {{ $p['color'] }}; border-color: {{ $p['color'] }}; color:#fff" @endif>
                    {{ $p['short'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div data-poll="lista" data-poll-url="{{ route('richieste.elenco') }}">
        @include('requests.partials.list')
    </div>
@endsection
