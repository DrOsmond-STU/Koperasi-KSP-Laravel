<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $branding['app_name'] }} — @yield('title', 'Cetakan')</title>
    <style>
        {{-- Paper size/orientation is set PHP-side via GeneratesPrintPdf::setPaperFrom()
             (Pdf::setPaper()) — the authoritative, reliably-supported mechanism in
             dompdf. @page here only controls margin, which dompdf does honor from CSS. --}}
        {{-- Ruang bawah dilebihkan 9mm untuk menampung nomor halaman, yang
             dipasang position:fixed dan karena itu tidak memesan ruang sendiri
             — tanpa margin tambahan ia akan menimpa baris terakhir tabel. --}}
        @page {
            margin: {{ $printSettings->margin_mm }}mm;
            margin-bottom: {{ $printSettings->margin_mm + 9 }}mm;
        }
        {{-- counter(page)/counter(pages) dihitung dompdf saat merangkai halaman,
             jadi jumlah halaman baru diketahui di akhir dan tetap benar. --}}
        .page-number {
            position: fixed;
            bottom: -7mm;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 8pt;
            color: #5C6E64;
        }
        .page-number:after {
            content: "Halaman " counter(page) " dari " counter(pages);
        }
        body { font-family: sans-serif; font-size: {{ $printSettings->font_size_pt }}pt; color: #16201C; }
        table { width: 100%; border-collapse: collapse; }
        .letterhead { width: 100%; border-bottom: 2px solid #11543B; padding-bottom: 10px; margin-bottom: 16px; }
        .letterhead td { vertical-align: middle; }
        .letterhead .logo-cell { width: 60px; }
        .letterhead img { max-height: 50px; max-width: 55px; }
        .letterhead .name-cell h1 { font-size: 15pt; margin: 0; color: #11543B; }
        .letterhead .name-cell p { font-size: 9pt; color: #5C6E64; margin: 2px 0 0; }
        .print-footer { margin-top: 24px; font-size: 8pt; color: #5C6E64; border-top: 1px solid #D7E2DB; padding-top: 6px; }
        .data-table th, .data-table td { border: 1px solid #D7E2DB; padding: 5px 7px; text-align: left; }
        .data-table th { background: #F4F7F4; font-weight: 700; }
    </style>
</head>
<body>
    @php $logoDataUri = app(\App\Services\Settings\BrandingService::class)->logoDataUri(); @endphp
    <table class="letterhead">
        <tr>
            @if ($logoDataUri)
                <td class="logo-cell"><img src="{{ $logoDataUri }}" alt="Logo"></td>
            @endif
            <td class="name-cell">
                <h1>{{ $branding['app_name'] }}</h1>
                @if ($printSettings->show_address && $printSettings->address_text)
                    <p>{{ $printSettings->address_text }}</p>
                @endif
            </td>
        </tr>
    </table>

    <div class="page-number"></div>

    @yield('print-content')

    @if ($printSettings->footer_text)
        <div class="print-footer">{{ $printSettings->footer_text }}</div>
    @endif
</body>
</html>
