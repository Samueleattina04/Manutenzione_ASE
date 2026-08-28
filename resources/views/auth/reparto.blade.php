<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#b5850b">
    <title>Scegli il reparto — Richiesta Manutenzione</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%A7%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-top"></div>
            <div class="login-inner">
                <div class="logo">👷</div>
                <h1>Accesso operatore</h1>
                <p class="sub">Seleziona il tuo reparto</p>

                @if($errors->any())
                    <div class="inline-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('entra.operatore') }}" data-guard>
                    @csrf
                    <div class="field">
                        <label>Reparto <span class="req">*</span></label>
                        <select name="reparto" required autofocus>
                            <option value="" disabled selected>Scegli il reparto…</option>
                            @foreach($reparti as $rp)
                                <option value="{{ $rp }}" @selected(old('reparto') === $rp)>{{ $rp }}</option>
                            @endforeach
                        </select>
                        <div class="hint">Vedrai le richieste aperte dagli operatori di questo reparto.</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Entra</button>
                </form>

                <div class="login-hint">
                    <a href="{{ route('entra') }}" class="login-back">← Torna alla scelta del profilo</a>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
