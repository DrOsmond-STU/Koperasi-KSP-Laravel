<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFixedAssetCategoryRequest;
use App\Models\ChartOfAccount;
use App\Models\FixedAssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FixedAssetCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.kategori-aktiva-tetap.index', [
            'categories' => FixedAssetCategory::query()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.kategori-aktiva-tetap.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreFixedAssetCategoryRequest $request): RedirectResponse
    {
        $category = FixedAssetCategory::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.master.fixed-asset-categories.index')
            ->with('status', "Kategori aktiva tetap \"{$category->name}\" berhasil dibuat.");
    }
}
