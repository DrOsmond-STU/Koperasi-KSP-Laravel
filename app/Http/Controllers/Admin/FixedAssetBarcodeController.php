<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesBarcodePng;
use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Label barcode Aktiva Tetap, untuk ditempel di fisik aset — satu/banyak/
 * semua aset (?asset_id[]=, kosong = semua aset aktif). Sejajar
 * ProductBarcodeController.
 */
class FixedAssetBarcodeController extends Controller
{
    use GeneratesBarcodePng;

    public function print(Request $request): Response
    {
        $this->authorize('aktiva_tetap.read');

        $assetIds = $request->input('asset_id', []);

        $assets = FixedAsset::query()
            ->when(! empty($assetIds), fn ($query) => $query->whereIn('id', $assetIds))
            ->when(empty($assetIds), fn ($query) => $query->where('status', 'aktif'))
            ->orderBy('name')
            ->get()
            ->map(fn (FixedAsset $asset) => [
                'code' => $asset->code,
                'name' => $asset->name,
                'barcode' => $this->barcodeDataUri($asset->code),
            ]);

        $pdf = Pdf::loadView('prints.barcode.labels', ['items' => $assets])->setPaper('a4', 'portrait');

        return $pdf->download('barcode-aktiva-tetap-'.now()->format('Ymd-His').'.pdf');
    }
}
