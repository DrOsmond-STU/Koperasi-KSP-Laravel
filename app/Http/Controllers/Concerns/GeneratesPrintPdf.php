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
 */
trait GeneratesPrintPdf
{
    /**
     * $orientation menimpa pilihan admin hanya untuk cetakan yang secara
     * bentuk memang tidak muat portrait (mis. tabel laporan berkolom banyak).
     * Dibiarkan null untuk semua cetakan lain, supaya pengaturan admin tetap
     * jadi penentu seperti sebelumnya.
     */
    protected function renderPrintPdf(string $view, array $data = [], ?string $orientation = null): PdfDocument
    {
        $printSettings = app(PrintSettingsService::class)->current();

        $paperSize = match ($printSettings->paper_size) {
            'letter' => 'letter',
            'f4' => [0, 0, 595.28, 935.43], // 210mm x 330mm in points
            default => 'a4',
        };

        return Pdf::loadView($view, $data)
            ->setPaper($paperSize, $orientation ?? $printSettings->orientation);
    }
}
