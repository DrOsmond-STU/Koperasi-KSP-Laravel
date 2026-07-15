<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanProductRequest;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.produk-pinjaman.index', [
            'products' => LoanProduct::query()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.produk-pinjaman.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreLoanProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = DB::transaction(function () use ($data) {
            $product = LoanProduct::query()->create([
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
            ->route('admin.master.loan-products.index')
            ->with('status', "Produk pinjaman \"{$product->name}\" berhasil dibuat.");
    }
}
