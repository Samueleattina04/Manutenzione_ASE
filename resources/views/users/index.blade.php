@extends('layouts.app')
@section('title', 'Gestione utenti')

@section('content')
@php($roles = ['operatore', 'manutentore', 'manutentore_esterno', 'admin'])
@php($ruoliLabel = config('manutenzione.ruoli'))

<div style="display:flex; align-items:center; gap:12px; margin-bottom:16px">
    <h2 style="margin:0; flex:1">Gestione utenti</h2>
    <button class="btn btn-primary" data-modal-open="modal-new" type="button">➕ Nuovo utente</button>
</div>

@if($errors->any())
    <div class="inline-error">{{ $errors->first() }}</div>
@endif

<div class="table-wrap">
    <table class="users">
        <thead>
            <tr><th>Nome</th><th>Username</th><th>Email</th><th>Ruolo</th><th>Stato</th><th>Azioni</th></tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr>
                    <td><strong>{{ $user->name }}</strong></td>
                    <td>{{ $user->username }}</td>
                    <td>{{ $user->email ?: '—' }}</td>
                    <td><span class="role-badge">{{ $ruoliLabel[$user->role] ?? $user->role }}</span></td>
                    <td>
                        @if($user->active)<span class="pill-on">Attivo</span>
                        @else<span class="pill-off">Disattivato</span>@endif
                    </td>
                    <td class="nowrap">
                        <button class="btn btn-ghost btn-sm" data-modal-open="edit-{{ $user->id }}" type="button">Modifica</button>
                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('utenti.toggle', $user) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">{{ $user->active ? 'Disattiva' : 'Riattiva' }}</button>
                            </form>
                        @endif
                        @if($user->id !== auth()->id() && $user->username !== config('manutenzione.guest_operator_username', 'operatore'))
                            <form method="POST" action="{{ route('utenti.destroy', $user) }}" style="display:inline"
                                  data-confirm="Eliminare l'utente «{{ $user->name }}»? L'operazione non è reversibile.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Elimina</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Modale: nuovo utente --}}
<div class="modal-overlay" id="modal-new" data-modal hidden>
    <div class="modal">
        <h3>Nuovo utente</h3>
        <form method="POST" action="{{ route('utenti.store') }}" data-guard>
            @csrf
            <div class="field"><label>Nome e cognome <span class="req">*</span></label><input type="text" name="name" required></div>
            <div class="field"><label>Username <span class="req">*</span></label><input type="text" name="username" required></div>
            <div class="field"><label>Email</label><input type="email" name="email" placeholder="Per i manutentori esterni: riceveranno le richieste via email"></div>
            <div class="field"><label>Ruolo <span class="req">*</span></label>
                <select name="role">@foreach($roles as $r)<option value="{{ $r }}">{{ $ruoliLabel[$r] ?? $r }}</option>@endforeach</select>
            </div>
            <div class="field"><label>Password <span class="req">*</span></label><input type="password" name="password" placeholder="min 6 caratteri" required></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-modal-close>Annulla</button>
                <button type="submit" class="btn btn-primary">Crea utente</button>
            </div>
        </form>
    </div>
</div>

{{-- Modali: modifica utente --}}
@foreach($users as $user)
    <div class="modal-overlay" id="edit-{{ $user->id }}" data-modal hidden>
        <div class="modal">
            <h3>Modifica utente</h3>
            <form method="POST" action="{{ route('utenti.update', $user) }}" data-guard>
                @csrf
                @method('PUT')
                <div class="field"><label>Nome e cognome <span class="req">*</span></label><input type="text" name="name" value="{{ $user->name }}" required></div>
                <div class="field"><label>Username</label><input type="text" value="{{ $user->username }}" disabled></div>
                <div class="field"><label>Email</label><input type="email" name="email" value="{{ $user->email }}" placeholder="Per i manutentori esterni"></div>
                <div class="field"><label>Ruolo <span class="req">*</span></label>
                    <select name="role" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        @foreach($roles as $r)<option value="{{ $r }}" @selected($user->role === $r)>{{ $ruoliLabel[$r] ?? $r }}</option>@endforeach
                    </select>
                </div>
                @if($user->id !== auth()->id())
                    <div class="field">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:600">
                            <input type="checkbox" name="active" value="1" style="width:auto" @checked($user->active)> Attivo
                        </label>
                    </div>
                @else
                    <input type="hidden" name="active" value="1">
                @endif
                <div class="field"><label>Nuova password</label><input type="password" name="password" placeholder="Lascia vuoto per non cambiare"></div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" data-modal-close>Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
