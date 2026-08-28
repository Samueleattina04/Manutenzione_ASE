<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#b5850b">
    <title>Accedi — Richiesta Manutenzione</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%A7%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-top"></div>
            <div class="login-inner">
                <div class="logo">🔧</div>
                <h1>Richiesta Manutenzione</h1>
                <p class="sub">Chi sei?</p>

                @if($errors->any())
                    <div class="inline-error">{{ $errors->first() }}</div>
                @endif

                {{-- Operatore: sceglie il reparto e poi entra, senza credenziali --}}
                <a href="{{ route('entra.operatore.reparto') }}" class="role-choice role-op">
                    <span class="role-ic">👷</span>
                    <span class="role-txt">
                        <strong>Operatore</strong>
                        <small>Scegli il reparto ed entra, senza password</small>
                    </span>
                    <span class="role-arrow">→</span>
                </a>

                {{-- Manutentore / Amministratore: username e password --}}
                <a href="{{ route('login') }}" class="role-choice">
                    <span class="role-ic">🔧</span>
                    <span class="role-txt">
                        <strong>Manutentore</strong>
                        <small>Accesso con username e password</small>
                    </span>
                    <span class="role-arrow">→</span>
                </a>

                <a href="{{ route('login') }}" class="role-choice">
                    <span class="role-ic">🛡️</span>
                    <span class="role-txt">
                        <strong>Amministratore</strong>
                        <small>Accesso con username e password</small>
                    </span>
                    <span class="role-arrow">→</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
