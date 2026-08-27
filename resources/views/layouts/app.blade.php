<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#b5850b">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Richiesta Manutenzione')</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%A7%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@auth
    @php($u = auth()->user())
    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('richieste.index') }}" class="brand">
                <span class="logo">🔧</span>
                <span>Manutenzione<small>ASE</small></span>
            </a>
            <nav class="nav">
                <a href="{{ route('richieste.index') }}" class="{{ request()->routeIs('richieste.index') ? 'active' : '' }}">Richieste</a>
                <a href="{{ route('richieste.create') }}" class="{{ request()->routeIs('richieste.create') ? 'active' : '' }}">Nuova</a>
                @if($u->isAdmin())
                    <a href="{{ route('utenti.index') }}" class="{{ request()->routeIs('utenti.index') ? 'active' : '' }}">Utenti</a>
                @endif
            </nav>
            <div class="spacer"></div>
            <div class="usermenu">
                <button class="usermenu-btn" data-usermenu-btn type="button">
                    <span class="avatar">{{ \Illuminate\Support\Str::of($u->name)->explode(' ')->take(2)->map(fn($w)=>mb_substr($w,0,1))->implode('') }}</span>
                    <span>{{ \Illuminate\Support\Str::of($u->name)->explode(' ')->first() }}</span>
                </button>
                <div class="usermenu-panel" data-usermenu-panel hidden>
                    <div class="uinfo">
                        <strong>{{ $u->name }}</strong>
                        <span class="role-badge">{{ $u->role }}</span>
                    </div>
                    <a href="{{ route('profilo.password') }}">🔑 Cambia password</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">🚪 Esci</button>
                    </form>
                </div>
            </div>
        </div>
    </header>
@endauth

    <main class="container">
        @if(session('ok'))
            <div class="flash ok">{{ session('ok') }}</div>
        @endif
        @yield('content')
    </main>

@auth
    <nav class="mobile-nav">
        <a href="{{ route('richieste.index') }}" class="{{ request()->routeIs('richieste.index') ? 'active' : '' }}"><span class="ic">📋</span>Richieste</a>
        <a href="{{ route('richieste.create') }}" class="{{ request()->routeIs('richieste.create') ? 'active' : '' }}"><span class="ic">➕</span>Nuova</a>
        @if($u->isAdmin())
            <a href="{{ route('utenti.index') }}" class="{{ request()->routeIs('utenti.index') ? 'active' : '' }}"><span class="ic">👥</span>Utenti</a>
        @endif
    </nav>
@endauth

    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
