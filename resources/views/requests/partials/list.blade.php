<div class="stats">
    <div class="stat"><div class="n">{{ $stats['attive'] }}</div><div class="l">Richieste attive</div></div>
    <div class="stat alert"><div class="n">{{ $stats['urgenti'] }}</div><div class="l">Urgenti (rosso)</div></div>
    @if(auth()->user()->isAdmin())
        <a href="{{ route('richieste.index', ['da_assegnare' => 1, 'status' => 'tutte']) }}"
           class="stat {{ $stats['da_assegnare'] > 0 ? 'alert' : '' }}" style="text-decoration:none">
            <div class="n">{{ $stats['da_assegnare'] }}</div><div class="l">Esterne da assegnare</div>
        </a>
    @else
        <div class="stat"><div class="n">{{ $stats['mie'] }}</div><div class="l">Le mie richieste</div></div>
    @endif
</div>

@if($requests->isEmpty())
    <div class="empty">
        <div class="big">📭</div>
        @if(auth()->user()->isOperatore())
            <div>Nessuna richiesta per il reparto <strong>{{ session('op_reparto') }}</strong>.<br>Tocca <strong>Nuova</strong> per crearne una.</div>
        @else
            <div>Nessuna richiesta trovata con questi filtri.</div>
        @endif
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
                    @if($r->isEsterna())
                        <span class="badge" style="background:#6d4c41">Esterna</span>
                        @if($r->esternaDaAssegnare())
                            <span class="badge" style="background:#c62828">Da assegnare</span>
                        @endif
                    @endif
                </div>
            </a>
        @endforeach
    </div>
@endif
