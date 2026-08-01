<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\ChartOfAccount;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    use GeneratesPrintPdf;

    private const PHOTO_DISK = 'public';

    private const PHOTO_DIRECTORY = 'products';

    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.produk-barang.index', [
            'products' => Product::query()->latest()->get(),
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('master_data.read');

        $pdf = $this->renderPrintPdf('prints.master.produk-barang', [
            'products' => Product::query()->orderBy('name')->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('daftar-barang-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('master_data.read');

        $rows = Product::query()->orderBy('name')->get()->map(fn (Product $product) => [
            $product->code,
            $product->name,
            $product->category,
            $product->unit,
            (float) $product->selling_price,
            $product->is_active ? 'Aktif' : 'Nonaktif',
        ]);

        return Excel::download(
            new GenericListExport(['Kode', 'Nama', 'Kategori', 'Satuan', 'Harga Jual', 'Status'], $rows),
            'daftar-barang-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.produk-barang.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = collect($request->validated())->except('image')->all();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store(self::PHOTO_DIRECTORY, self::PHOTO_DISK);
        }

        $product = Product::query()->create([
            ...$data,
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.master.products.index')
            ->with('status', "Barang \"{$product->name}\" berhasil dibuat.");
    }

    public function edit(Product $product): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.produk-barang.edit', [
            'product' => $product,
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = collect($request->validated())->except('image')->all();

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk(self::PHOTO_DISK)->delete($product->image_path);
            }

            $data['image_path'] = $request->file('image')->store(self::PHOTO_DIRECTORY, self::PHOTO_DISK);
        }

        $product->update($data);

        return redirect()
            ->route('admin.master.products.index')
            ->with('status', "Barang \"{$product->name}\" berhasil diperbarui.");
    }
}
