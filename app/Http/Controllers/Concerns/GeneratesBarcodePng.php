<?php

namespace App\Http\Controllers\Concerns;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Renders Code128 barcodes as base64 PNG data URIs — embedded directly as
 * <img src="data:image/png;base64,...">, the most dompdf-reliable way to
 * get a barcode into a PDF (dompdf's inline-SVG support is inconsistent;
 * PNG via <img> is the same mechanism already used for the koperasi logo
 * in prints/layout.blade.php).
 */
trait GeneratesBarcodePng
{
    protected function barcodeDataUri(string $code): string
    {
        $generator = new BarcodeGeneratorPNG();
        $png = $generator->getBarcode($code, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 40);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
