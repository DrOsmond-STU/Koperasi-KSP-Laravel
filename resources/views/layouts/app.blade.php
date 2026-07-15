<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding['app_name'] }} — @yield('title', 'Beranda')</title>
    <style>
        :root {
            --pine: #11543B; --pine-deep: #0C3B29; --pine-ink: #16201C;
            --gold: #C68A2E; --gold-soft: #F3E6CC; --paper: #F4F7F4; --surface: #FFFFFF;
            --leaf: #E9F1EC; --line: #D7E2DB; --muted: #5C6E64; --muted-2: #869389;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--paper); color: var(--pine-ink); }

        /* A11Y-01: focus visible wajib terlihat di semua kontrol (03_BRAND.md). */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible,
        textarea:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 6px;
        }

        /* A11Y-04: matikan animasi/transisi bila pengguna minta gerak berkurang. */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
        }

        .topbar {
            height: 58px; background: var(--surface); border-bottom: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
            position: sticky; top: 0; z-index: 20;
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .sidebar-toggle {
            display: none; background: transparent; border: 1px solid var(--line); border-radius: 8px;
            width: 36px; height: 36px; cursor: pointer; color: var(--pine); font-size: 16px;
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

        .app-shell { display: flex; align-items: flex-start; }

        .sidebar-nav {
            width: 240px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--line);
            height: calc(100vh - 58px); position: sticky; top: 58px; overflow-y: auto; padding: 16px 10px;
        }
        .sidebar-group { margin-bottom: 18px; }
        .sidebar-group-label {
            /* A11Y-02: --muted-2 hanya 3.2:1 pada putih (di bawah ambang 4.5:1
               untuk teks kecil) — pakai --muted (5.4:1) untuk label ini. */
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
            color: var(--muted); margin: 0 10px 6px;
        }
        .sidebar-nav ul { list-style: none; margin: 0; padding: 0; }
        .sidebar-nav a {
            display: block; padding: 9px 10px; border-radius: 9px; font-size: 13.5px; font-weight: 600;
            color: var(--pine-ink); text-decoration: none;
        }
        .sidebar-nav a:hover { background: var(--leaf); }
        .sidebar-nav a[aria-current="page"] { background: var(--leaf); color: var(--pine); }

        .sidebar-backdrop { display: none; }

        main { flex: 1; min-width: 0; padding: 24px; }

        @media (max-width: 980px) {
            .sidebar-toggle { display: inline-flex; align-items: center; justify-content: center; }

            .sidebar-nav {
                position: fixed; top: 0; left: 0; height: 100vh; z-index: 40; width: 260px;
                transform: translateX(-100%); transition: transform 200ms ease; box-shadow: 2px 0 12px rgba(0,0,0,.12);
                padding-top: 70px;
            }
            body.sidebar-open .sidebar-nav { transform: translateX(0); }

            .sidebar-backdrop {
                display: none; position: fixed; inset: 0; background: rgba(22,32,28,.4); z-index: 30;
            }
            body.sidebar-open .sidebar-backdrop { display: block; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="sidebar-toggle" id="sidebar-toggle-btn" aria-label="Buka menu navigasi" aria-controls="sidebar-nav" aria-expanded="false">☰</button>
            <div class="topbar-brand">
                @if($branding['logo_url'])
                    <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['app_name'] }}">
                @else
                    <div class="fallback-logo">{{ mb_substr($branding['app_name'], 0, 1) }}</div>
                @endif
                <span>{{ $branding['app_name'] }}</span>
            </div>
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

    <div class="app-shell">
        @auth
            @include('partials.sidebar-nav')
            <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
        @endauth

        <main>
            @yield('content')
        </main>
    </div>

    @auth
        <script>
            (function () {
                var toggle = document.getElementById('sidebar-toggle-btn');
                var backdrop = document.getElementById('sidebar-backdrop');

                function closeSidebar() {
                    document.body.classList.remove('sidebar-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }

                toggle.addEventListener('click', function () {
                    var isOpen = document.body.classList.toggle('sidebar-open');
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
                backdrop.addEventListener('click', closeSidebar);
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') closeSidebar();
                });
            })();
        </script>
    @endauth
</body>
</html>
