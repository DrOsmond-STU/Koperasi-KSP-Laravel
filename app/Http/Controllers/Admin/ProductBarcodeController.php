<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesBarcodePng;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Label barcode Master Barang — satu/banyak/semua produk (?product_id[]=,
 * kosong = semua produk aktif). Bukan surat berkop — lembar stiker label
 * kompak, tidak memakai prints/layout.blade.php.
 */
class ProductBarcodeController extends Controller
{
    use GeneratesBarcodePng;

    public function print(Request $request): Response
    {
        $this->authorize('master_data.read');

        $productIds = $request->input('product_id', []);

        $products = Product::query()
            ->when(! empty($productIds), fn ($query) => $query->whereIn('id', $productIds))
            ->when(empty($productIds), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'code' => $product->code,
                'name' => $product->name,
                'barcode' => $this->barcodeDataUri($product->code),
            ]);

        $pdf = Pdf::loadView('prints.barcode.labels', ['items' => $products])->setPaper('a4', 'portrait');

        return $pdf->download('barcode-barang-'.now()->format('Ymd-His').'.pdf');
    }
}
