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
                <p class="sub">Accedi per gestire le richieste</p>

                @if($errors->any())
                    <div class="inline-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" data-guard>
                    @csrf
                    <div class="field">
                        <label>Username</label>
                        <input type="text" name="username" value="{{ old('username') }}" autocomplete="username" autofocus required>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Accedi</button>
                </form>

                <div class="login-hint">Accesso riservato al personale autorizzato.</div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
