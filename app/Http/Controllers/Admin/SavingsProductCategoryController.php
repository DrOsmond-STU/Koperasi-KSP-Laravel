<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSavingsProductCategoryRequest;
use App\Http\Requests\UpdateSavingsProductCategoryRequest;
use App\Models\SavingsProduct;
use App\Models\SavingsProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Master Kategori Produk Simpanan — pola sama dengan MemberTypeController.
 *
 * Sebelum ini kategori terkunci sebagai enum di kode sehingga koperasi tidak
 * bisa menambah kategori sendiri (mis. "Retribusi Pasar" dari sistem lama).
 */
class SavingsProductCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.kategori-simpanan.index', [
            'categories' => SavingsProductCategory::query()->orderBy('name')->get(),
            'productCounts' => SavingsProduct::query()
                ->selectRaw('category, count(*) as aggregate')
                ->groupBy('category')
                ->pluck('aggregate', 'category'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.kategori-simpanan.create');
    }

    public function store(StoreSavingsProductCategoryRequest $request): RedirectResponse
    {
        $category = SavingsProductCategory::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.master.savings-product-categories.index')
            ->with('status', "Kategori simpanan \"{$category->name}\" berhasil dibuat.");
    }

    public function edit(SavingsProductCategory $savingsProductCategory): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.kategori-simpanan.edit', [
            'category' => $savingsProductCategory,
        ]);
    }

    public function update(UpdateSavingsProductCategoryRequest $request, SavingsProductCategory $savingsProductCategory): RedirectResponse
    {
        $savingsProductCategory->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.master.savings-product-categories.index')
            ->with('status', "Kategori simpanan \"{$savingsProductCategory->name}\" berhasil diperbarui.");
    }

    public function destroy(SavingsProductCategory $savingsProductCategory): RedirectResponse
    {
        $this->authorize('master_data.update');

        // Kategori dirujuk lewat kolom `code`, bukan foreign key, jadi tidak
        // ada constraint database yang menahan penghapusan — guard ini yang
        // mencegah produk simpanan menunjuk kategori yang sudah tidak ada.
        $usageCount = SavingsProduct::query()
            ->where('category', $savingsProductCategory->code)
            ->count();

        if ($usageCount > 0) {
            return redirect()
                ->route('admin.master.savings-product-categories.index')
                ->with('error', "Kategori \"{$savingsProductCategory->name}\" tidak bisa dihapus — masih dipakai oleh {$usageCount} produk simpanan. Nonaktifkan saja lewat menu Ubah.");
        }

        $name = $savingsProductCategory->name;
        $savingsProductCategory->delete();

        return redirect()
            ->route('admin.master.savings-product-categories.index')
            ->with('status', "Kategori simpanan \"{$name}\" berhasil dihapus.");
    }
}
