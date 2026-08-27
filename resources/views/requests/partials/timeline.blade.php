@if($req->updates->isEmpty())
    <div class="muted" style="font-size:14px; margin-bottom:8px">
        Nessun intervento registrato. In attesa della presa in carico.
    </div>
@endif

<div class="timeline">
    {{-- Apertura --}}
    <div class="tl-item">
        <div class="tl-head">
            <span class="tl-who">{{ $req->operatore ?: 'Operatore' }}</span>
            <span class="tl-when">· {{ $req->created_at->format('d/m/Y H:i') }}</span>
        </div>
        <p class="tl-note">Richiesta aperta.</p>
    </div>

    {{-- Interventi --}}
    @foreach($req->updates as $u)
        @php($done = $u->status ? config('manutenzione.stati.'.$u->status.'.done', false) : false)
        <div class="tl-item {{ $done ? 'done' : '' }}">
            <div class="tl-head">
                <span class="tl-who">{{ $u->user?->name ?? 'Manutentore' }}</span>
                @if($u->status)<x-stato-badge :value="$u->status" />@endif
                <span class="tl-when">· {{ $u->created_at->format('d/m/Y H:i') }}</span>
            </div>
            @if($u->note)<p class="tl-note">{{ $u->note }}</p>@endif
            @if($u->attachments->count())
                <div class="tl-photos">
                    @include('requests.partials.photos', ['photos' => $u->attachments])
                </div>
            @endif
        </div>
    @endforeach
</div>
