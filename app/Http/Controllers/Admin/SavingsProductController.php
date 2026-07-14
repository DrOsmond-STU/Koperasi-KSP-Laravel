<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSavingsProductRequest;
use App\Models\ChartOfAccount;
use App\Models\SavingsProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SavingsProductController extends Controller
{
    public function index(): View
    {
        return view('admin.master.produk-simpanan.index', [
            'products' => SavingsProduct::query()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.produk-simpanan.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreSavingsProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = DB::transaction(function () use ($data) {
            $product = SavingsProduct::query()->create([
                ...collect($data)->except(['initial_rate_percentage', 'initial_rate_effective_from'])->all(),
                'is_active' => true,
            ]);

            $product->rateHistory()->create([
                'rate_percentage' => $data['initial_rate_percentage'],
                'effective_from' => $data['initial_rate_effective_from'] ?? now()->toDateString(),
            ]);

            return $product;
        });

        return redirect()
            ->route('admin.master.savings-products.index')
            ->with('status', "Produk simpanan \"{$product->name}\" berhasil dibuat.");
    }
}
