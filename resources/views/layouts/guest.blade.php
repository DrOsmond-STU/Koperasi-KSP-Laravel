<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding['app_name'] }}</title>
    <style>
        /* Tema A "Institusional Modern" (default/fallback) — lihat layouts/app.blade.php
           utk penjelasan lengkap arsitektur token dual-tema ini. */
        :root {
            --pine: #11543B; --pine-deep: #0C3B29; --pine-ink: #16201C;
            --gold: #C68A2E; --gold-soft: #F3E6CC;
            --ok: #2E7D52; --brick: #A8472F;
            --paper: #F4F7F4; --surface: #FFFFFF; --line: #D7E2DB; --muted: #5C6E64;
            --card-shadow: 0 1px 2px rgba(16,32,25,.04), 0 20px 40px -20px rgba(16,32,25,.18), 0 0 50px -22px rgba(37,99,235,.18);
            --body-bg-image: radial-gradient(at 15% 10%, rgba(37,99,235,.10) 0%, transparent 55%), radial-gradient(at 85% 90%, rgba(59,130,246,.08) 0%, transparent 50%);
            --heading-font: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
            --chrome-glass: rgba(255,255,255,.72);
            color-scheme: light;
            accent-color: var(--pine);
        }
        /* Tema B "Cinematic Bold" — latar gelap, glow emerald+emas, kaca. */
        :root[data-theme="b"] {
            --pine: #1B7A5A; --pine-deep: #123F2E; --pine-ink: #F1F5F2;
            --gold: #E4B15C; --gold-soft: #3A2E17;
            --ok: #34C48D; --brick: #E4876A;
            --paper: #0A1512; --surface: #131F1A; --line: #4B7057; --muted: #9FB3A8;
            --card-shadow: 0 1px 2px rgba(0,0,0,.4), 0 20px 48px -16px rgba(0,0,0,.6), 0 0 60px -20px rgba(52,196,141,.25);
            --body-bg-image: radial-gradient(at 15% 10%, rgba(52,196,141,.16) 0%, transparent 55%), radial-gradient(at 85% 90%, rgba(228,177,92,.12) 0%, transparent 50%);
            --heading-font: Georgia, "Iowan Old Style", "Palatino Linotype", "Book Antiqua", serif;
            --chrome-glass: rgba(19,31,26,.72);
            color-scheme: dark;
            accent-color: var(--pine);
        }
        input, select, textarea {
            background: var(--surface); color: var(--pine-ink); font-family: inherit;
        }
        body {
            margin: 0; font-family: 'Inter', system-ui, sans-serif; color: var(--pine-ink);
            background-image: var(--body-bg-image);
            background-color: var(--paper);
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; min-height: 100vh;
        }
        h1 { font-family: var(--heading-font); }

        /* A11Y-01: focus visible wajib terlihat di semua kontrol. */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible,
        textarea:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 6px;
        }

        /* A11Y-04: matikan animasi/transisi bila pengguna minta gerak berkurang. */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
        }
        .guest-card {
            background: var(--chrome-glass); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--line); border-radius: 14px;
            box-shadow: var(--card-shadow);
            padding: 32px; width: 100%; max-width: 380px;
        }
        .theme-toggle-wrap { position: fixed; top: 16px; right: 16px; }
        .theme-toggle { display: inline-flex; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; }
        .theme-toggle-btn {
            background: transparent; border: none; padding: 6px 12px; font-size: 11px; font-weight: 700;
            color: var(--muted); cursor: pointer; font-family: inherit;
        }
        .theme-toggle-btn[aria-pressed="true"] { background: var(--pine); color: #fff; }
        .guest-brand { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 24px; }
        .guest-brand img { max-height: 56px; max-width: 100%; }
        .guest-brand .fallback-logo {
            width: 48px; height: 48px; border-radius: 12px;
            background: linear-gradient(160deg, var(--pine), var(--pine-deep));
            color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px;
        }
        .guest-brand h1 { font-size: 17px; font-weight: 800; margin: 0; text-align: center; letter-spacing: -0.01em; }
        .guest-footer {
            display: flex; flex-direction: column; align-items: center; gap: 2px;
            text-align: center; font-size: 11px; color: var(--muted);
        }
        .guest-footer .brand-footer-name { font-weight: 800; letter-spacing: 0.06em; color: var(--pine); }
    </style>
</head>
<body>
    <div class="theme-toggle-wrap">
        @include('partials.theme-toggle')
    </div>
    <div class="guest-card">
        <div class="guest-brand">
            @if($branding['logo_url'])
                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['app_name'] }}">
            @else
                <div class="fallback-logo">{{ mb_substr($branding['app_name'], 0, 1) }}</div>
            @endif
            <h1>{{ $branding['app_name'] }}</h1>
        </div>

        @yield('content')
    </div>

    <footer class="guest-footer">
        @include('partials.brand-footer')
    </footer>
</body>
</html>
