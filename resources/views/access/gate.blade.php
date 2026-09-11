<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#b5850b">
    <title>Accesso — Manutenzione ASE</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%A7%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-top"></div>
            <div class="login-inner">
                <div class="logo">🔒</div>
                <h1>Manutenzione ASE</h1>
                <p class="sub">Inserisci il codice di accesso aziendale</p>

                @if($errors->any())
                    <div class="inline-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('accesso.submit') }}" data-guard>
                    @csrf
                    <div class="field">
                        <label>Codice di accesso</label>
                        <input type="password" name="codice" autocomplete="off" autofocus required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Entra</button>
                </form>

                <p class="sub" style="margin-top:16px; font-size:12px">
                    Ti verrà chiesto una sola volta su questo dispositivo.
                </p>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
