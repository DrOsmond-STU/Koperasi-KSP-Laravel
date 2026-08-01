@php
    // Minimal hand-drawn icon set (24x24, stroke-based, same visual
    // language as the sidebar's existing chevron SVG) — no external icon
    // library dependency, consistent with the rest of this app.
    $icons = [
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
        'wallet' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 12h4"/><path d="M2 10h20"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14c2.5.3 4.5 2.4 4.5 6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'repeat' => '<path d="M4 7h13l-3-3"/><path d="M20 17H7l3 3"/>',
        'send' => '<path d="M3 11l18-8-8 18-3-7-7-3z"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
        'piggy-bank' => '<path d="M4 12a5 5 0 015-5h6a5 5 0 015 5v2a2 2 0 01-2 2h-1l-1 3H9l-1-3H6a2 2 0 01-2-2v-2z"/><circle cx="16" cy="11" r="1"/><path d="M4 12H2"/>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
        'book' => '<path d="M4 4h11a3 3 0 013 3v13H7a3 3 0 01-3-3V4z"/><path d="M4 4v13a3 3 0 003 3"/>',
        'file-text' => '<path d="M6 3h9l5 5v13H6z"/><path d="M15 3v5h5"/><path d="M9 12h6"/><path d="M9 16h6"/>',
        'sliders' => '<path d="M4 6h10M18 6h2"/><path d="M4 12h4M12 12h8"/><path d="M4 18h13M21 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'bar-chart' => '<path d="M4 20V10"/><path d="M12 20V4"/><path d="M20 20v-7"/><path d="M2 20h20"/>',
        'pie-chart' => '<path d="M12 2v10l8 4a10 10 0 10-8-14z"/>',
        'trending-up' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'download' => '<path d="M12 3v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'package' => '<path d="M3 8l9-5 9 5-9 5-9-5z"/><path d="M3 8v9l9 5 9-5V8"/><path d="M12 13v9"/>',
        'truck' => '<rect x="2" y="7" width="13" height="9" rx="1"/><path d="M15 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'shopping-cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 4h3l3 12h11l3-8H6"/>',
        'rotate-ccw' => '<path d="M4 4v6h6"/><path d="M5 13a8 8 0 108-11l-5 4"/>',
        'monitor' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/>',
        'clipboard' => '<rect x="6" y="4" width="12" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/>',
        'bar-chart-2' => '<path d="M6 20V13"/><path d="M12 20V4"/><path d="M18 20v-9"/>',
        'folder' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
        'archive' => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v9a2 2 0 002 2h10a2 2 0 002-2V9"/><path d="M10 13h4"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'tag' => '<path d="M20 12l-8 8-9-9V4h7z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>',
        'shield' => '<path d="M12 3l7 3v6c0 5-3 8-7 9-4-1-7-4-7-9V6z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 6l9 7 9-7"/>',
        'bell' => '<path d="M6 10a6 6 0 0112 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 20a2 2 0 004 0"/>',
        'shield-check' => '<path d="M12 3l7 3v6c0 5-3 8-7 9-4-1-7-4-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 15l-5-5-9 9"/>',
        'default' => '<circle cx="12" cy="12" r="9"/>',
    ];

    $svgInner = $icons[$name ?? 'default'] ?? $icons['default'];
    $iconSize = $size ?? 18;
@endphp
<svg class="nav-icon" viewBox="0 0 24 24" width="{{ $iconSize }}" height="{{ $iconSize }}" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $svgInner !!}</svg>
