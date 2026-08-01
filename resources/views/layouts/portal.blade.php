<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding['app_name'] }} — @yield('title', 'Portal Anggota')</title>
    <style>
        /* Token dual-tema sama persis dengan layouts/app.blade.php — lihat
           file itu untuk penjelasan lengkap arsitekturnya. Portal ini
           sengaja punya chrome sendiri yang jauh lebih ringkas (5 menu
           datar, tanpa sidebar) karena ditujukan untuk anggota, bukan
           staf/admin — lihat plan "Portal Anggota". */
        :root {
            --pine: #11543B; --pine-deep: #0C3B29; --pine-ink: #16201C; --pine-bright: #11543B;
            --gold: #C68A2E; --gold-soft: #F3E6CC; --gold-deep: #9C6A1E;
            --ok: #2E7D52; --brick: #A8472F; --brick-soft: #F2DED7;
            --paper: #F4F7F4; --surface: #FFFFFF;
            --leaf: #E9F1EC; --leaf-2: #DCE9E0; --line: #D7E2DB; --muted: #5C6E64; --muted-2: #869389;
            --shadow: 0 1px 2px rgba(16,32,25,.04), 0 8px 24px -12px rgba(16,32,25,.16);
            --nav-active-shadow: 0 4px 12px -6px rgba(37,99,235,.25);
            --card-shadow: 0 1px 2px rgba(16,32,25,.04), 0 20px 40px -20px rgba(16,32,25,.18), 0 0 50px -22px rgba(37,99,235,.18);
            --body-bg-image: radial-gradient(at 15% -10%, rgba(37,99,235,.08) 0%, transparent 55%), radial-gradient(at 85% 100%, rgba(59,130,246,.06) 0%, transparent 50%);
            --heading-font: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
            --chrome-glass: rgba(255,255,255,.72);
            color-scheme: light;
            accent-color: var(--pine);
        }
        :root[data-theme="b"] {
            --pine: #1B7A5A; --pine-deep: #123F2E; --pine-ink: #F1F5F2; --pine-bright: #4CC99A;
            --gold: #E4B15C; --gold-soft: #3A2E17; --gold-deep: #8A6A32;
            --ok: #34C48D; --brick: #E4876A; --brick-soft: #3A2016;
            --paper: #0A1512; --surface: #131F1A;
            --leaf: #16261E; --leaf-2: #223629; --line: #4B7057; --muted: #9FB3A8; --muted-2: #6B8177;
            --shadow: 0 1px 2px rgba(0,0,0,.4), 0 8px 24px -12px rgba(0,0,0,.5);
            --nav-active-shadow: 0 0 0 1px rgba(52,196,141,.3), 0 4px 16px -6px rgba(27,122,90,.55);
            --card-shadow: 0 1px 2px rgba(0,0,0,.4), 0 20px 48px -16px rgba(0,0,0,.6), 0 0 60px -20px rgba(52,196,141,.25);
            --body-bg-image: radial-gradient(at 12% -8%, rgba(52,196,141,.14) 0%, transparent 55%), radial-gradient(at 90% 6%, rgba(228,177,92,.10) 0%, transparent 50%);
            --heading-font: Georgia, "Iowan Old Style", "Palatino Linotype", "Book Antiqua", serif;
            --chrome-glass: rgba(19,31,26,.72);
            color-scheme: dark;
            accent-color: var(--pine);
        }
        * { box-sizing: border-box; }
        input, select, textarea { background: var(--surface); color: var(--pine-ink); font-family: inherit; }
        body {
            margin: 0; font-family: 'Inter', system-ui, sans-serif; color: var(--pine-ink);
            background-image: var(--body-bg-image); background-color: var(--paper); background-attachment: fixed;
            min-height: 100vh; display: flex; flex-direction: column;
        }
        h1, h2, h3, h4 { font-family: var(--heading-font); }

        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible,
        textarea:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 6px;
        }
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
        }

        .topbar {
            height: 58px; background: var(--chrome-glass); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
            position: sticky; top: 0; z-index: 20;
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; }
        .topbar-brand img { max-height: 32px; }
        .topbar-brand .fallback-logo {
            width: 28px; height: 28px; border-radius: 8px; background: var(--pine); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;
        }
        .topbar-brand span { font-weight: 800; font-size: 15px; letter-spacing: -0.01em; }
        .topbar-actions { display: flex; align-items: center; gap: 10px; }
        .topbar-actions a, .topbar-actions form button {
            background: transparent; border: 1px solid var(--line); color: var(--pine-bright);
            border-radius: 9px; padding: 6px 14px; font-size: 13px; text-decoration: none; cursor: pointer; font-family: inherit;
        }
        .theme-toggle { display: inline-flex; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; }
        .theme-toggle-btn {
            background: transparent; border: none; padding: 6px 12px; font-size: 11px; font-weight: 700;
            color: var(--muted); cursor: pointer; font-family: inherit;
        }
        .theme-toggle-btn[aria-pressed="true"] { background: var(--pine); color: #fff; }

        .portal-nav {
            background: var(--chrome-glass); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
            display: flex; gap: 4px; padding: 8px 20px; overflow-x: auto;
        }
        .portal-nav a {
            display: flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 9px;
            font-size: 13.5px; font-weight: 600; color: var(--pine-ink); text-decoration: none; white-space: nowrap;
        }
        .portal-nav a:hover { background: var(--leaf); }
        .portal-nav a[aria-current="page"] { background: var(--pine); color: #fff; box-shadow: var(--nav-active-shadow); }

        main { flex: 1; padding: 24px; max-width: 1100px; margin: 0 auto; width: 100%; }

        .app-footer {
            text-align: center; padding: 16px 20px; font-size: 12px; color: var(--muted);
            border-top: 1px solid var(--line);
            display: flex; flex-direction: column; align-items: center; gap: 2px;
        }
        .app-footer .brand-footer-name { font-weight: 800; letter-spacing: 0.06em; color: var(--pine-bright); }
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
            <span>{{ $branding['app_name'] }} — Portal Anggota</span>
        </div>
        <div class="topbar-actions">
            @include('partials.theme-toggle')
            <a href="{{ route('profile.edit') }}">Profil Saya</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </div>
    </header>

    <nav class="portal-nav" aria-label="Navigasi Portal Anggota">
        <a href="{{ route('portal.dashboard') }}" @if(request()->routeIs('portal.dashboard')) aria-current="page" @endif>
            @include('partials.icon', ['name' => 'home'])
            Dashboard Saya
        </a>
        <a href="{{ route('portal.savings.index') }}" @if(request()->routeIs('portal.savings.*')) aria-current="page" @endif>
            @include('partials.icon', ['name' => 'piggy-bank'])
            Simpanan Saya
        </a>
        <a href="{{ route('portal.loans.index') }}" @if(request()->routeIs('portal.loans.*')) aria-current="page" @endif>
            @include('partials.icon', ['name' => 'credit-card'])
            Pinjaman Saya
        </a>
        <a href="{{ route('portal.loan-application.create') }}" @if(request()->routeIs('portal.loan-application.*')) aria-current="page" @endif>
            @include('partials.icon', ['name' => 'send'])
            Pengajuan Pinjaman
        </a>
        <a href="{{ route('portal.withdrawal-request.create') }}" @if(request()->routeIs('portal.withdrawal-request.*')) aria-current="page" @endif>
            @include('partials.icon', ['name' => 'log-out'])
            Pengajuan Penarikan
        </a>
    </nav>

    <main>
        @yield('content')
    </main>

    <footer class="app-footer">
        @include('partials.brand-footer')
    </footer>
</body>
</html>
