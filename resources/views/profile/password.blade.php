@extends('layouts.app')
@section('title', 'Cambia password')

@section('content')
<div class="card form-card">
    <h2 style="margin-top:0">Cambia password</h2>

    @if($errors->any())
        <div class="inline-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('profilo.password.update') }}" data-guard>
        @csrf
        @method('PUT')
        <div class="field">
            <label>Password attuale <span class="req">*</span></label>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="field">
            <label>Nuova password <span class="req">*</span></label>
            <input type="password" name="password" autocomplete="new-password" placeholder="min 6 caratteri" required>
        </div>
        <div class="field">
            <label>Conferma nuova password <span class="req">*</span></label>
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Aggiorna password</button>
    </form>
</div>
@endsection
