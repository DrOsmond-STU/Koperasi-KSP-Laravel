<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding['app_name'] }}</title>
    <style>
        :root {
            --pine: #11543B; --pine-deep: #0C3B29; --pine-ink: #16201C;
            --gold: #C68A2E; --gold-soft: #F3E6CC;
            --paper: #F4F7F4; --surface: #FFFFFF; --line: #D7E2DB; --muted: #5C6E64;
        }
        body {
            margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--paper);
            color: var(--pine-ink); display: flex; align-items: center; justify-content: center; min-height: 100vh;
        }

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
            background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            box-shadow: 0 1px 2px rgba(16,32,25,.04), 0 8px 24px -12px rgba(16,32,25,.16);
            padding: 32px; width: 100%; max-width: 380px;
        }
        .guest-brand { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 24px; }
        .guest-brand img { max-height: 56px; max-width: 100%; }
        .guest-brand .fallback-logo {
            width: 48px; height: 48px; border-radius: 12px;
            background: linear-gradient(160deg, var(--pine), var(--pine-deep));
            color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px;
        }
        .guest-brand h1 { font-size: 17px; font-weight: 800; margin: 0; text-align: center; letter-spacing: -0.01em; }
    </style>
</head>
<body>
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
</body>
</html>
