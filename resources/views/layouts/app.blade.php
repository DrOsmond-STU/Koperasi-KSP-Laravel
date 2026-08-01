<!DOCTYPE html>
<html lang="id" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $branding['app_name'] }} — @yield('title', 'Beranda')</title>
    <style>
        /* Tema A "Institusional Modern" (default/fallback) — identitas
           pine+gold terang asli (05_BRAND.md v2) + aksen gradient biru. */
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
        /* Tema B "Cinematic Bold" — latar gelap, glow emerald+emas, kaca. */
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
        input, select, textarea {
            background: var(--surface); color: var(--pine-ink); font-family: inherit;
        }
        body {
            margin: 0; font-family: 'Inter', system-ui, sans-serif; color: var(--pine-ink);
            background-image: var(--body-bg-image);
            background-color: var(--paper);
            background-attachment: fixed;
        }
        h1, h2, h3, h4 { font-family: var(--heading-font); }

        /* A11Y-01: focus visible wajib terlihat di semua kontrol (03_BRAND.md). */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible,
        textarea:focus-visible, [tabindex]:focus-visible, summary:focus-visible {
            outline: 2px solid var(--gold); outline-offset: 2px; border-radius: 6px;
        }

        /* A11Y-04: matikan animasi/transisi bila pengguna minta gerak berkurang. */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
        }

        .topbar {
            height: 58px; background: var(--chrome-glass); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--line);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
            position: sticky; top: 0; z-index: 20;
        }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .sidebar-toggle {
            display: none; background: transparent; border: 1px solid var(--line); border-radius: 8px;
            width: 36px; height: 36px; cursor: pointer; color: var(--pine-bright); font-size: 16px;
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; }
        .topbar-brand img { max-height: 32px; }
        .topbar-brand .fallback-logo {
            width: 28px; height: 28px; border-radius: 8px; background: var(--pine); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;
        }
        .topbar-brand span { font-weight: 800; font-size: 15px; letter-spacing: -0.01em; }
        .topbar-actions { display: flex; align-items: center; gap: 10px; }
        .topbar-actions form button {
            background: transparent; border: 1px solid var(--line); color: var(--pine-bright);
            border-radius: 9px; padding: 6px 14px; font-size: 13px; cursor: pointer;
        }
        .theme-toggle { display: inline-flex; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; }
        .theme-toggle-btn {
            background: transparent; border: none; padding: 6px 12px; font-size: 11px; font-weight: 700;
            color: var(--muted); cursor: pointer; font-family: inherit;
        }
        .theme-toggle-btn[aria-pressed="true"] { background: var(--pine); color: #fff; }

        .app-shell { display: flex; align-items: flex-start; }

        .sidebar-nav {
            width: 240px; flex-shrink: 0; background: var(--chrome-glass); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border-right: 1px solid var(--line);
            height: calc(100vh - 58px); position: sticky; top: 58px; overflow-y: auto; padding: 16px 10px;
        }
        .sidebar-group { margin-bottom: 18px; }
        .sidebar-group-label {
            /* A11Y-02: --muted-2 hanya 3.2:1 pada putih (di bawah ambang 4.5:1
               untuk teks kecil) — pakai --muted (5.4:1) untuk label ini. */
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
            color: var(--muted); margin: 0 10px 6px;
        }
        /* Accordion: tiap grup selain "Utama" dibungkus <details>/<summary>
           native — dapat toggle keyboard/screen-reader gratis tanpa JS.
           Deklarasi display:none eksplisit (bukan cuma andalkan UA
           stylesheet default) supaya collapse konsisten di semua browser. */
        .sidebar-group-collapsible:not([open]) > ul { display: none; }
        .sidebar-group-collapsible { margin-bottom: 4px; }
        .sidebar-group-collapsible > summary.sidebar-group-label {
            display: flex; align-items: center; justify-content: space-between;
            cursor: pointer; list-style: none; padding: 6px 10px; margin: 0 0 2px; border-radius: 8px;
        }
        .sidebar-group-collapsible > summary.sidebar-group-label::-webkit-details-marker { display: none; }
        .sidebar-group-collapsible > summary.sidebar-group-label:hover { background: var(--leaf); }
        .sidebar-group-chevron { flex-shrink: 0; transition: transform 150ms ease; }
        .sidebar-group-collapsible[open] > summary .sidebar-group-chevron { transform: rotate(90deg); }
        .sidebar-nav ul { list-style: none; margin: 0; padding: 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; font-size: 13.5px; font-weight: 600;
            color: var(--pine-ink); text-decoration: none;
        }
        .sidebar-nav a:hover { background: var(--leaf); }
        .sidebar-nav a[aria-current="page"] {
            background: var(--pine); color: #fff;
            box-shadow: var(--nav-active-shadow);
        }
        .nav-icon { flex-shrink: 0; }

        .sidebar-collapse-row { display: flex; justify-content: flex-end; padding: 0 6px 10px; }
        .sidebar-collapse-btn {
            background: transparent; border: 1px solid var(--line); border-radius: 8px;
            width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--muted);
        }
        .sidebar-collapse-btn:hover { background: var(--leaf); color: var(--pine-bright); }
        .sidebar-collapse-icon { transition: transform 150ms ease; }

        /* Icon-only rail: labels stay in the DOM (visually-hidden, not
           display:none) so screen readers still announce them — sighted
           users get the native `title` tooltip on hover instead. */
        body.sidebar-collapsed .sidebar-label {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
        }
        body.sidebar-collapsed .sidebar-nav { width: 64px; padding: 16px 8px; }
        body.sidebar-collapsed .sidebar-nav a { justify-content: center; padding: 10px 0; }
        body.sidebar-collapsed .sidebar-group-chevron { display: none; }
        body.sidebar-collapsed .sidebar-group-collapsible:not([open]) > ul { display: block; }
        body.sidebar-collapsed .sidebar-collapse-row { justify-content: center; }
        body.sidebar-collapsed .sidebar-collapse-icon { transform: rotate(180deg); }

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

            /* The icon-only rail is a desktop space-saving affordance —
               on mobile the sidebar is already fully off-canvas until
               opened, so always show it full-width with labels here,
               regardless of the desktop collapse cookie carried over. */
            .sidebar-collapse-row { display: none; }
            body.sidebar-collapsed .sidebar-nav { width: 260px; padding: 16px 10px; padding-top: 70px; }
            body.sidebar-collapsed .sidebar-nav a { justify-content: flex-start; padding: 9px 10px; }
            body.sidebar-collapsed .sidebar-label {
                position: static; width: auto; height: auto; padding: 0; margin: 0;
                overflow: visible; clip: auto; white-space: normal; border: 0;
            }
            body.sidebar-collapsed .sidebar-group-chevron { display: block; }
        }

        .app-footer {
            text-align: center; padding: 16px 20px; font-size: 12px; color: var(--muted);
            border-top: 1px solid var(--line);
            display: flex; flex-direction: column; align-items: center; gap: 2px;
        }
        .app-footer .brand-footer-name { font-weight: 800; letter-spacing: 0.06em; color: var(--pine-bright); }

        /* Dropdown Pencarian — progressive enhancement untuk <select
           class="js-searchable"> yang opsinya berasal dari data yang bisa
           bertambah banyak (akun COA, anggota, produk, dsb). Select asli
           tetap dirender (transparan, menumpuk tepat di atas kotak teks)
           supaya validasi HTML "required" bawaan browser tetap berfungsi
           tanpa perlu disalin manual ke elemen baru. */
        .ss-wrap { position: relative; }
        .ss-wrap select.js-searchable { position: absolute; inset: 0; opacity: 0; pointer-events: none; }
        .ss-input { width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid var(--line); border-radius: 9px; font-family: inherit; background: var(--surface); color: var(--pine-ink); }
        .ss-menu {
            position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 50;
            background: var(--surface); border: 1px solid var(--line); border-radius: 9px;
            box-shadow: var(--shadow); max-height: 240px; overflow-y: auto;
        }
        .ss-option { padding: 8px 12px; font-size: 13px; cursor: pointer; }
        .ss-option:hover, .ss-option--active { background: var(--leaf); }
        .ss-option--selected { font-weight: 700; }
        .ss-empty { padding: 8px 12px; font-size: 13px; color: var(--muted); }
        .ss-wrap--multi .ss-tags {
            display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
            padding: 6px; border: 1px solid var(--line); border-radius: 9px; background: var(--surface);
        }
        .ss-chip {
            display: inline-flex; align-items: center; gap: 4px; background: var(--leaf); color: var(--pine);
            border-radius: 999px; padding: 3px 6px 3px 10px; font-size: 12px; font-weight: 600;
        }
        .ss-chip-remove { background: transparent; border: none; color: var(--pine); cursor: pointer; font-size: 14px; line-height: 1; padding: 0 2px; }
        .ss-wrap--multi .ss-input { border: none; padding: 4px; flex: 1; min-width: 80px; }

        /* Tooltip interaktif untuk grafik SVG inline (dashboard/laporan) —
           dipakai oleh <div class="chart-wrap"> mana pun yang berisi elemen
           [data-tooltip] (JSON: {title, rows:[{label,value,color,strong?}]}).
           Hover/fokus pada batang, titik garis, arc donut, atau item legend
           menampilkan info tersebut; hover legend meredupkan seri lain. */
        .chart-wrap { position: relative; }
        .chart-legend-item[data-tooltip] { cursor: pointer; border-radius: 6px; padding: 2px 6px; margin: -2px -6px; transition: background .15s; }
        .chart-legend-item[data-tooltip]:hover, .chart-legend-item[data-tooltip]:focus-visible { background: var(--paper); outline: none; }
        .act-mark { transition: filter .12s ease, opacity .15s ease; cursor: pointer; }
        .act-mark:hover, .act-mark:focus-visible { filter: brightness(1.2); outline: none; }
        .act-mark.is-dim { opacity: .2; }
        .chart-tooltip {
            position: absolute; pointer-events: none; opacity: 0; transform: translateY(4px);
            transition: opacity .12s ease, transform .12s ease;
            background: var(--pine-ink); color: #fff; padding: 8px 10px; border-radius: 8px;
            font-size: 12px; line-height: 1.5; box-shadow: 0 6px 18px rgba(0,0,0,.2);
            z-index: 20; min-width: 170px; top: 0; left: 0;
        }
        .chart-tooltip.is-visible { opacity: 1; transform: translateY(0); }
        .chart-tooltip-title { font-weight: 700; margin-bottom: 4px; font-size: 11px; color: rgba(255,255,255,.7); }
        .chart-tooltip-row { display: flex; align-items: center; gap: 6px; padding: 1px 0; }
        .chart-tooltip-key { width: 10px; height: 2px; border-radius: 2px; flex-shrink: 0; }
        .chart-tooltip-label { flex: 1; color: rgba(255,255,255,.85); }
        .chart-tooltip-value { font-weight: 700; font-variant-numeric: tabular-nums; }

        /* KPI card klik-untuk-detail — dipakai oleh <div class="kpi-card"
           data-detail-target="id-panel"> mana pun; menampilkan/menyembunyikan
           <div class="kpi-detail" id="id-panel"> yang duduk sebagai sibling
           tepat setelah .kpi-grid induknya (lihat JS di bawah body). */
        .kpi-icon { display: inline-flex; margin-bottom: 8px; opacity: .9; }
        .kpi-card[data-detail-target] { cursor: pointer; transition: transform .1s ease, box-shadow .15s ease; }
        .kpi-card[data-detail-target]:hover, .kpi-card[data-detail-target]:focus-visible { transform: translateY(-2px); box-shadow: var(--shadow); outline: none; }
        .kpi-detail-panels { margin-bottom: 20px; }
        .kpi-detail { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; margin-bottom: 20px; }
        .kpi-detail h4 { margin: 0 0 10px; font-size: 13px; }

        /* Dialog info klik — dipakai oleh elemen mana pun dengan
           [data-dialog] (JSON: {title, rows:[{label,value}]}), mis. item
           kalender. Satu overlay dibagi bersama seluruh halaman. */
        .info-dialog-backdrop {
            position: fixed; inset: 0; background: rgba(16,32,25,.45); z-index: 100;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            opacity: 0; pointer-events: none; transition: opacity .15s ease;
        }
        .info-dialog-backdrop.is-visible { opacity: 1; pointer-events: auto; }
        .info-dialog {
            background: var(--surface); border-radius: 14px; box-shadow: var(--card-shadow, var(--shadow));
            max-width: 360px; width: 100%; padding: 20px; transform: translateY(8px); transition: transform .15s ease;
        }
        .info-dialog-backdrop.is-visible .info-dialog { transform: translateY(0); }
        .info-dialog-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
        .info-dialog-title { font-size: 15px; font-weight: 700; color: var(--pine-ink); margin: 0; }
        .info-dialog-close { background: transparent; border: none; font-size: 18px; line-height: 1; color: var(--muted); cursor: pointer; padding: 2px 4px; }
        .info-dialog-close:hover, .info-dialog-close:focus-visible { color: var(--pine-ink); outline: none; }
        .info-dialog-row { display: flex; justify-content: space-between; gap: 14px; padding: 7px 0; border-bottom: 1px solid var(--line); font-size: 13px; }
        .info-dialog-row:last-child { border-bottom: none; }
        .info-dialog-label { color: var(--muted); }
        .info-dialog-value { font-weight: 600; text-align: right; }

        /* Datatable generik — dipakai oleh <div class="dt-wrap" data-dt> mana
           pun (menu Laporan). Search box mencocokkan teks seluruh baris;
           setiap <select class="dt-filter" data-filter-column="key"> mencocokkan
           <td data-column="key"> yang sama secara exact-match. Semua filter +
           search digabung AND, murni client-side di atas tabel yang sudah
           dirender server (lihat JS di bawah body). */
        .dt-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px; }
        .dt-search, .dt-filter { padding: 8px 12px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; background: var(--surface); color: inherit; }
        .dt-search { flex: 1 1 220px; min-width: 180px; }
        .dt-period { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: var(--muted); }
        .dt-period input[type="date"] { padding: 7px 9px; border: 1px solid var(--line); border-radius: 9px; font-size: 13px; background: var(--surface); color: inherit; }
        .dt-count { font-size: 12px; color: var(--muted); margin-left: auto; white-space: nowrap; }
        .dt-table-scroll { overflow-x: auto; }
        .dt-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; }
        .dt-table th, .dt-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); font-size: 13px; white-space: nowrap; }
        .dt-table th { background: var(--paper); font-weight: 700; color: var(--muted); position: sticky; top: 0; }
        .dt-table tbody tr:last-child td { border-bottom: none; }
        .dt-table tbody tr.dt-row-hidden { display: none; }
        .dt-empty { text-align: center; color: var(--muted); font-size: 13px; padding: 24px 0; }
    </style>
</head>
<body @class(['sidebar-collapsed' => $sidebarCollapsed])>
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
            @include('partials.theme-toggle')
            @auth
                <a href="{{ route('profile.edit') }}" style="background: transparent; border: 1px solid var(--line); color: var(--pine-bright); border-radius: 9px; padding: 6px 14px; font-size: 13px; text-decoration: none;">Profil Saya</a>
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

    <footer class="app-footer">
        @include('partials.brand-footer')
    </footer>

    <div class="info-dialog-backdrop" id="info-dialog-backdrop">
        <div class="info-dialog" role="dialog" aria-modal="true" aria-labelledby="info-dialog-title">
            <div class="info-dialog-header">
                <h4 class="info-dialog-title" id="info-dialog-title"></h4>
                <button type="button" class="info-dialog-close" id="info-dialog-close" aria-label="Tutup">&times;</button>
            </div>
            <div id="info-dialog-body"></div>
        </div>
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

                var collapseBtn = document.getElementById('sidebar-collapse-btn');
                if (collapseBtn) {
                    collapseBtn.addEventListener('click', function () {
                        var isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                        collapseBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                        collapseBtn.setAttribute('aria-label', isCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
                        document.cookie = 'sidebar_collapsed=' + (isCollapsed ? '1' : '0') + ';path=/;max-age=31536000;samesite=Lax';
                    });
                }
            })();
        </script>
    @endauth

    <script>
        /* Dropdown Pencarian — lihat komentar CSS ".ss-wrap" di <head>.
           Select asli tidak pernah dihapus/disembunyikan total (display:none)
           — hanya ditumpuk transparan di atas kotak teks — supaya:
           1) validasi "required" bawaan browser tetap jalan tanpa perlu
              disalin manual, dan 2) name/value yang terkirim ke server
              persis sama seperti sebelum select ini disentuh sama sekali. */
        (function () {
            function optionLabel(option) {
                return option.textContent.trim();
            }

            function closeOnOutsideClick(wrap, close) {
                document.addEventListener('mousedown', function (event) {
                    if (!wrap.contains(event.target)) close();
                });
            }

            function buildMenu(select, matches, onPick) {
                var menu = document.createElement('div');
                menu.className = 'ss-menu';
                menu.hidden = true;

                var highlightIndex = -1;

                function render(list) {
                    menu.innerHTML = '';
                    if (list.length === 0) {
                        var empty = document.createElement('div');
                        empty.className = 'ss-empty';
                        empty.textContent = 'Tidak ada hasil';
                        menu.appendChild(empty);
                    } else {
                        list.slice(0, 200).forEach(function (option) {
                            var item = document.createElement('div');
                            item.className = 'ss-option' + (option.value === select.value ? ' ss-option--selected' : '');
                            item.textContent = optionLabel(option);
                            item.addEventListener('mousedown', function (event) {
                                event.preventDefault();
                                onPick(option);
                            });
                            menu.appendChild(item);
                        });
                    }
                    highlightIndex = -1;
                }

                function open(filterText) {
                    render(matches(filterText));
                    menu.hidden = false;
                }

                function close() {
                    menu.hidden = true;
                    highlightIndex = -1;
                }

                function highlight(delta) {
                    var items = menu.querySelectorAll('.ss-option');
                    if (!items.length) return;
                    highlightIndex = (highlightIndex + delta + items.length) % items.length;
                    items.forEach(function (el, i) { el.classList.toggle('ss-option--active', i === highlightIndex); });
                    items[highlightIndex].scrollIntoView({ block: 'nearest' });
                }

                function pickHighlighted() {
                    var items = menu.querySelectorAll('.ss-option');
                    if (highlightIndex >= 0 && items[highlightIndex]) items[highlightIndex].dispatchEvent(new MouseEvent('mousedown'));
                }

                return { el: menu, open: open, close: close, highlight: highlight, pickHighlighted: pickHighlighted };
            }

            function enhanceSingle(select) {
                var wrap = document.createElement('div');
                wrap.className = 'ss-wrap';

                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'ss-input';
                input.autocomplete = 'off';
                var placeholderOption = select.options[0];
                input.placeholder = (placeholderOption && placeholderOption.value === '') ? optionLabel(placeholderOption) : 'Cari...';

                select.parentNode.insertBefore(wrap, select);
                wrap.appendChild(input);
                wrap.appendChild(select);

                function realOptions() {
                    return Array.prototype.filter.call(select.options, function (o) { return o.value !== ''; });
                }

                function matches(filterText) {
                    var q = (filterText || '').toLowerCase();
                    return realOptions().filter(function (o) { return optionLabel(o).toLowerCase().indexOf(q) !== -1; });
                }

                function syncInputFromSelect() {
                    var selected = select.options[select.selectedIndex];
                    input.value = (selected && selected.value !== '') ? optionLabel(selected) : '';
                }

                var menu = buildMenu(select, matches, function (option) {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncInputFromSelect();
                    menu.close();
                });
                wrap.appendChild(menu.el);

                input.addEventListener('focus', function () { menu.open(''); });
                input.addEventListener('input', function () { menu.open(input.value); });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown') { event.preventDefault(); menu.el.hidden ? menu.open(input.value) : menu.highlight(1); }
                    else if (event.key === 'ArrowUp') { event.preventDefault(); menu.highlight(-1); }
                    else if (event.key === 'Enter') { event.preventDefault(); menu.pickHighlighted(); }
                    else if (event.key === 'Escape') { menu.close(); input.blur(); }
                });
                input.addEventListener('blur', function () { setTimeout(function () { menu.close(); syncInputFromSelect(); }, 150); });
                closeOnOutsideClick(wrap, menu.close);

                syncInputFromSelect();
            }

            function enhanceMulti(select) {
                var wrap = document.createElement('div');
                wrap.className = 'ss-wrap ss-wrap--multi';

                var tags = document.createElement('div');
                tags.className = 'ss-tags';

                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'ss-input';
                input.autocomplete = 'off';
                input.placeholder = 'Cari...';

                select.parentNode.insertBefore(wrap, select);
                wrap.appendChild(tags);
                tags.appendChild(input);
                wrap.appendChild(select);

                function allOptions() {
                    return Array.prototype.slice.call(select.options);
                }

                function renderTags() {
                    Array.prototype.slice.call(tags.querySelectorAll('.ss-chip')).forEach(function (el) { el.remove(); });
                    allOptions().filter(function (o) { return o.selected; }).forEach(function (option) {
                        var chip = document.createElement('span');
                        chip.className = 'ss-chip';
                        chip.textContent = optionLabel(option) + ' ';

                        var remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'ss-chip-remove';
                        remove.setAttribute('aria-label', 'Hapus ' + optionLabel(option));
                        remove.textContent = '×';
                        remove.addEventListener('click', function () {
                            option.selected = false;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            renderTags();
                        });

                        chip.appendChild(remove);
                        tags.insertBefore(chip, input);
                    });
                }

                function matches(filterText) {
                    var q = (filterText || '').toLowerCase();
                    return allOptions().filter(function (o) {
                        return o.value !== '' && !o.selected && optionLabel(o).toLowerCase().indexOf(q) !== -1;
                    });
                }

                var menu = buildMenu(select, matches, function (option) {
                    option.selected = true;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    input.value = '';
                    renderTags();
                    menu.open('');
                    input.focus();
                });
                wrap.appendChild(menu.el);

                input.addEventListener('focus', function () { menu.open(input.value); });
                input.addEventListener('input', function () { menu.open(input.value); });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown') { event.preventDefault(); menu.el.hidden ? menu.open(input.value) : menu.highlight(1); }
                    else if (event.key === 'ArrowUp') { event.preventDefault(); menu.highlight(-1); }
                    else if (event.key === 'Enter') { event.preventDefault(); menu.pickHighlighted(); }
                    else if (event.key === 'Backspace' && input.value === '') {
                        var lastRemove = tags.querySelector('.ss-chip:last-of-type .ss-chip-remove');
                        if (lastRemove) lastRemove.click();
                    } else if (event.key === 'Escape') { menu.close(); input.blur(); }
                });
                input.addEventListener('blur', function () { setTimeout(menu.close, 150); });
                closeOnOutsideClick(wrap, menu.close);

                renderTags();
            }

            function enhance(select) {
                if (select.dataset.ssEnhanced) return;
                select.dataset.ssEnhanced = '1';
                select.multiple ? enhanceMulti(select) : enhanceSingle(select);
            }

            // Diekspos untuk baris <select class="js-searchable"> yang
            // ditambahkan lewat JS setelah pemuatan halaman (mis. baris
            // jurnal umum yang di-clone via tombol "+ Tambah Baris") —
            // enhancer di atas hanya berjalan sekali saat halaman dimuat.
            window.enhanceSearchableSelect = enhance;

            document.querySelectorAll('select.js-searchable').forEach(enhance);
        })();
    </script>
    <script>
        // Tooltip interaktif untuk grafik SVG inline — lihat komentar CSS
        // ".chart-wrap" di <head>. Setiap <div class="chart-wrap"> di
        // halaman manapun (dashboard, laporan) otomatis dilengkapi lewat
        // delegasi ini, tidak perlu JS per halaman.
        (function () {
            function initChartTooltip(wrap) {
                const tooltip = wrap.querySelector('.chart-tooltip');
                if (!tooltip) return;
                const marks = wrap.querySelectorAll('[data-tooltip]');

                function renderTooltip(data) {
                    tooltip.innerHTML = '';

                    const title = document.createElement('div');
                    title.className = 'chart-tooltip-title';
                    title.textContent = data.title;
                    tooltip.appendChild(title);

                    data.rows.forEach(function (row) {
                        const rowEl = document.createElement('div');
                        rowEl.className = 'chart-tooltip-row';

                        const key = document.createElement('span');
                        key.className = 'chart-tooltip-key';
                        key.style.background = row.color;

                        const label = document.createElement('span');
                        label.className = 'chart-tooltip-label';
                        label.textContent = row.label;

                        const value = document.createElement('span');
                        value.className = 'chart-tooltip-value' + (row.strong ? ' is-strong' : '');
                        value.textContent = row.value;

                        rowEl.append(key, label, value);
                        tooltip.appendChild(rowEl);
                    });
                }

                function positionTooltip(clientX, clientY) {
                    const wrapRect = wrap.getBoundingClientRect();
                    let x = clientX - wrapRect.left + 14;
                    let y = clientY - wrapRect.top + 14;
                    tooltip.style.left = x + 'px';
                    tooltip.style.top = y + 'px';

                    requestAnimationFrame(function () {
                        const tRect = tooltip.getBoundingClientRect();
                        if (tRect.right > wrapRect.right) {
                            tooltip.style.left = Math.max(0, x - tRect.width - 28) + 'px';
                        }
                        if (tRect.bottom > wrapRect.bottom) {
                            tooltip.style.top = Math.max(0, y - tRect.height - 28) + 'px';
                        }
                    });
                }

                function showFor(el, clientX, clientY) {
                    let data;
                    try {
                        data = JSON.parse(el.dataset.tooltip);
                    } catch (e) {
                        return;
                    }

                    renderTooltip(data);
                    tooltip.classList.add('is-visible');

                    if (typeof clientX === 'number') {
                        positionTooltip(clientX, clientY);
                    } else {
                        const rect = el.getBoundingClientRect();
                        positionTooltip(rect.left + rect.width / 2, rect.top);
                    }

                    if (el.classList.contains('chart-legend-item')) {
                        const series = el.dataset.series;
                        wrap.querySelectorAll('.act-mark').forEach(function (mark) {
                            mark.classList.toggle('is-dim', mark.dataset.series !== series);
                        });
                    }
                }

                function hide() {
                    tooltip.classList.remove('is-visible');
                    wrap.querySelectorAll('.act-mark.is-dim').forEach(function (mark) {
                        mark.classList.remove('is-dim');
                    });
                }

                marks.forEach(function (el) {
                    el.addEventListener('pointerenter', function (evt) { showFor(el, evt.clientX, evt.clientY); });
                    el.addEventListener('pointermove', function (evt) { positionTooltip(evt.clientX, evt.clientY); });
                    el.addEventListener('pointerleave', hide);
                    el.addEventListener('focus', function () { showFor(el); });
                    el.addEventListener('blur', hide);
                });
            }

            document.querySelectorAll('.chart-wrap').forEach(initChartTooltip);
        })();
    </script>
    <script>
        // KPI card klik-untuk-detail — lihat komentar CSS ".kpi-icon" di
        // <head>. Setiap <div class="kpi-card" data-detail-target="..."> di
        // halaman manapun otomatis dapat perilaku ini; panel detail yang
        // cocok harus duduk sebagai sibling tepat setelah .kpi-grid induknya
        // di dalam <div class="kpi-detail-panels">.
        (function () {
            function closeGroup(panels, grid) {
                panels.querySelectorAll('.kpi-detail').forEach(function (p) { p.hidden = true; });
                grid.querySelectorAll('[data-detail-target]').forEach(function (c) {
                    c.setAttribute('aria-expanded', 'false');
                });
            }

            function toggle(card) {
                const grid = card.closest('.kpi-grid');
                const panels = grid ? grid.nextElementSibling : null;
                if (!grid || !panels || !panels.classList.contains('kpi-detail-panels')) return;

                const target = document.getElementById(card.dataset.detailTarget);
                if (!target) return;

                const wasOpen = !target.hidden;
                closeGroup(panels, grid);
                if (!wasOpen) {
                    target.hidden = false;
                    card.setAttribute('aria-expanded', 'true');
                }
            }

            document.querySelectorAll('.kpi-card[data-detail-target]').forEach(function (card) {
                card.addEventListener('click', function () { toggle(card); });
                card.addEventListener('keydown', function (evt) {
                    if (evt.key === 'Enter' || evt.key === ' ') {
                        evt.preventDefault();
                        toggle(card);
                    }
                });
            });
        })();
    </script>
    <script>
        // Dialog info klik — lihat komentar CSS ".info-dialog-backdrop" di
        // <head>. Elemen mana pun dengan [data-dialog] (JSON:
        // {title, rows:[{label,value}]}) membuka dialog terpusat ini saat
        // diklik/di-Enter, tanpa perlu markup dialog per halaman.
        (function () {
            const backdrop = document.getElementById('info-dialog-backdrop');
            if (!backdrop) return;

            const titleEl = document.getElementById('info-dialog-title');
            const bodyEl = document.getElementById('info-dialog-body');
            const closeBtn = document.getElementById('info-dialog-close');
            let lastFocused = null;

            function open(data) {
                titleEl.textContent = data.title || '';
                bodyEl.innerHTML = '';

                (data.rows || []).forEach(function (row) {
                    const rowEl = document.createElement('div');
                    rowEl.className = 'info-dialog-row';

                    const label = document.createElement('span');
                    label.className = 'info-dialog-label';
                    label.textContent = row.label;

                    const value = document.createElement('span');
                    value.className = 'info-dialog-value';
                    value.textContent = row.value;

                    rowEl.append(label, value);
                    bodyEl.appendChild(rowEl);
                });

                lastFocused = document.activeElement;
                backdrop.classList.add('is-visible');
                closeBtn.focus();
            }

            function close() {
                backdrop.classList.remove('is-visible');
                if (lastFocused && typeof lastFocused.focus === 'function') {
                    lastFocused.focus();
                }
            }

            document.querySelectorAll('[data-dialog]').forEach(function (el) {
                el.addEventListener('click', function () {
                    try {
                        open(JSON.parse(el.dataset.dialog));
                    } catch (e) { /* ignore malformed dialog data */ }
                });
                el.addEventListener('keydown', function (evt) {
                    if (evt.key === 'Enter' || evt.key === ' ') {
                        evt.preventDefault();
                        el.click();
                    }
                });
            });

            closeBtn.addEventListener('click', close);
            backdrop.addEventListener('click', function (evt) {
                if (evt.target === backdrop) close();
            });
            document.addEventListener('keydown', function (evt) {
                if (evt.key === 'Escape' && backdrop.classList.contains('is-visible')) close();
            });
        })();
    </script>
    <script>
        // Datatable generik — lihat komentar CSS ".dt-toolbar" di <head>.
        // Setiap <div class="dt-wrap" data-dt> di halaman diinisialisasi
        // di sini secara independen (satu halaman Laporan hanya punya satu,
        // tapi ini tetap aman untuk banyak instance sekaligus).
        function initDataTable(wrap) {
            const searchInput = wrap.querySelector('.dt-search');
            const filterSelects = wrap.querySelectorAll('.dt-filter');
            const dateColumn = wrap.dataset.dateColumn || null;
            const dateFromInput = wrap.querySelector('.dt-date-from');
            const dateToInput = wrap.querySelector('.dt-date-to');
            const table = wrap.querySelector('.dt-table');
            const emptyEl = wrap.querySelector('.dt-empty');
            const countEl = wrap.querySelector('.dt-count');
            if (!table) return;

            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const total = rows.length;

            // Baris tanggal dirender server-side sebagai "d-m-Y" atau
            // "d-m-Y H:i" (format Indonesia) — diubah ke "yyyy-mm-dd" supaya
            // bisa dibandingkan leksikal dengan value <input type="date">.
            function parseRowDate(text) {
                const m = (text || '').trim().match(/^(\d{2})-(\d{2})-(\d{4})/);
                return m ? (m[3] + '-' + m[2] + '-' + m[1]) : null;
            }

            function apply() {
                const term = (searchInput?.value || '').trim().toLowerCase();
                const activeFilters = Array.from(filterSelects)
                    .map(function (select) { return { column: select.dataset.filterColumn, value: select.value }; })
                    .filter(function (filter) { return filter.value !== ''; });
                const dateFrom = dateFromInput?.value || '';
                const dateTo = dateToInput?.value || '';

                let visible = 0;

                rows.forEach(function (row) {
                    const matchesSearch = term === '' || row.textContent.toLowerCase().includes(term);
                    const matchesFilters = activeFilters.every(function (filter) {
                        const cell = row.querySelector('[data-column="' + filter.column + '"]');
                        return cell && cell.textContent.trim() === filter.value;
                    });

                    let matchesDate = true;
                    if (dateColumn && (dateFrom || dateTo)) {
                        const cell = row.querySelector('[data-column="' + dateColumn + '"]');
                        const rowDate = cell ? parseRowDate(cell.textContent) : null;
                        if (rowDate) {
                            if (dateFrom && rowDate < dateFrom) matchesDate = false;
                            if (dateTo && rowDate > dateTo) matchesDate = false;
                        }
                    }

                    const show = matchesSearch && matchesFilters && matchesDate;
                    row.classList.toggle('dt-row-hidden', !show);
                    if (show) visible++;
                });

                if (emptyEl) emptyEl.hidden = visible !== 0;
                if (countEl) countEl.textContent = 'Menampilkan ' + visible + ' dari ' + total + ' data';
            }

            searchInput?.addEventListener('input', apply);
            filterSelects.forEach(function (select) { select.addEventListener('change', apply); });
            dateFromInput?.addEventListener('change', apply);
            dateToInput?.addEventListener('change', apply);

            apply();
        }

        document.querySelectorAll('.dt-wrap[data-dt]').forEach(initDataTable);
    </script>
</body>
</html>
