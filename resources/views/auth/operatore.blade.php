<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#b5850b">
    <title>Accesso operatore — Richiesta Manutenzione</title>
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
                <p class="sub">{{ $pinRichiesto ? 'Inserisci il PIN operatori' : 'Entra senza password' }}</p>

                @if($errors->any())
                    <div class="inline-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('entra.operatore') }}" data-guard>
                    @csrf
                    @if($pinRichiesto)
                        <div class="field">
                            <label>PIN operatori <span class="req">*</span></label>
                            <input type="password" name="pin" inputmode="numeric" autocomplete="off"
                                   placeholder="Codice operatori" autofocus required>
                            <div class="hint">Chiedi il PIN al tuo responsabile.</div>
                        </div>
                    @endif
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
