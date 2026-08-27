<div class="stats">
    <div class="stat"><div class="n">{{ $stats['attive'] }}</div><div class="l">Richieste attive</div></div>
    <div class="stat alert"><div class="n">{{ $stats['urgenti'] }}</div><div class="l">Urgenti (rosso)</div></div>
    <div class="stat"><div class="n">{{ $stats['mie'] }}</div><div class="l">Le mie richieste</div></div>
</div>

@if($requests->isEmpty())
    <div class="empty">
        <div class="big">📭</div>
        <div>Nessuna richiesta trovata con questi filtri.</div>
    </div>
@else
    <div class="cards">
        @foreach($requests as $r)
            <a class="rcard p-{{ $r->priorita }}" href="{{ route('richieste.show', $r) }}">
                <div class="rcard-head">
                    <h3>{{ $r->macchinario }}</h3>
                    <span class="rcard-id">#{{ $r->id }}</span>
                </div>
                <div class="rcard-meta">
                    <span>🏭 {{ $r->impiantoLabel() }}</span>
                    @if($r->reparto)<span>📍 {{ $r->reparto }}</span>@endif
                    <span>👤 {{ $r->operatore }}</span>
                    <span>🕒 {{ $r->created_at->diffForHumans() }}</span>
                    @if($r->attachments_count)<span>📷 {{ $r->attachments_count }}</span>@endif
                    @if($r->assignee)<span>🔧 {{ $r->assignee->name }}</span>@endif
                </div>
                <div class="rcard-badges">
                    <x-priorita-badge :value="$r->priorita" />
                    <x-stato-badge :value="$r->status" />
                </div>
            </a>
        @endforeach
    </div>
@endif
