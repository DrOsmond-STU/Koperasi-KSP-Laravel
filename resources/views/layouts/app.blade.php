<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding['app_name'] }} — @yield('title', 'Beranda')</title>
    <style>
        :root {
            --pine: #11543B; --pine-deep: #0C3B29; --pine-ink: #16201C;
            --gold: #C68A2E; --paper: #F4F7F4; --surface: #FFFFFF; --line: #D7E2DB; --muted: #5C6E64;
        }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--paper); color: var(--pine-ink); }
        .topbar {
            height: 58px; background: var(--surface); border-bottom: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; }
        .topbar-brand img { max-height: 32px; }
        .topbar-brand .fallback-logo {
            width: 28px; height: 28px; border-radius: 8px; background: var(--pine); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;
        }
        .topbar-brand span { font-weight: 800; font-size: 15px; letter-spacing: -0.01em; }
        .topbar-actions form button {
            background: transparent; border: 1px solid var(--line); color: var(--pine);
            border-radius: 9px; padding: 6px 14px; font-size: 13px; cursor: pointer;
        }
        main { padding: 24px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-brand">
            @if($branding['logo_url'])
                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['app_name'] }}">
            @else
                <div class="fallback-logo">{{ mb_substr($branding['app_name'], 0, 1) }}</div>
            @endif
            <span>{{ $branding['app_name'] }}</span>
        </div>
        <div class="topbar-actions">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Keluar</button>
                </form>
            @endauth
        </div>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
