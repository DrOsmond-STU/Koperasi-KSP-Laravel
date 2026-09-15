<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Settings\PrintSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Shared by every cetakan controller — resolves App\Models\PrintSetting's
 * paper_size/orientation into Pdf::setPaper() (the reliably-honored,
 * PHP-side mechanism in dompdf), so the same admin-configured paper choice
 * applies uniformly across all ~20 print views without repeating this
 * match expression in each controller.
 *
 * $orientationOverride is a rare per-cetakan opt-out for reports that MUST
 * render in a specific orientation regardless of the admin's default
 * (misal: rekap ringkas UPF selalu portrait meski default landscape).
 */
trait GeneratesPrintPdf
{
    protected function renderPrintPdf(string $view, array $data = [], ?string $orientationOverride = null): PdfDocument
    {
        $printSettings = app(PrintSettingsService::class)->current();

        $paperSize = match ($printSettings->paper_size) {
            'letter' => 'letter',
            'f4' => [0, 0, 595.28, 935.43], // 210mm x 330mm in points
            default => 'a4',
        };

        $orientation = $orientationOverride ?? $printSettings->orientation;

        return Pdf::loadView($view, $data)->setPaper($paperSize, $orientation);
    }
}
