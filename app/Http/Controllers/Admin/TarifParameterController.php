<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddLoanRateHistoryRequest;
use App\Http\Requests\AddSavingsRateHistoryRequest;
use App\Http\Requests\UpdateLoanProductParametersRequest;
use App\Http\Requests\UpdateSavingsProductParametersRequest;
use App\Models\LoanProduct;
use App\Models\SavingsProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tarif & Parameter (PRD §7.3, Task 5.1). Every write here goes through
 * the product model's normal update()/rateHistory()->create() calls —
 * before/after audit trail (AUD-02) comes for free from the Auditable
 * trait already on SavingsProduct/LoanProduct, nothing bespoke needed here.
 */
class TarifParameterController extends Controller
{
    public function index(): View
    {
        $this->authorize('tarif.read');

        return view('admin.tarif-parameter.index', [
            'savingsProducts' => SavingsProduct::query()->with('rateHistory')->get(),
            'loanProducts' => LoanProduct::query()->with('rateHistory')->get(),
        ]);
    }

    public function updateSavings(UpdateSavingsProductParametersRequest $request, SavingsProduct $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('admin.tarif-parameter.index')
            ->with('status', "Parameter produk \"{$product->name}\" berhasil diperbarui.");
    }

    public function addSavingsRate(AddSavingsRateHistoryRequest $request, SavingsProduct $product): RedirectResponse
    {
        $product->rateHistory()->create($request->validated());

        return redirect()
            ->route('admin.tarif-parameter.index')
            ->with('status', "Tarif baru untuk \"{$product->name}\" berlaku sejak {$request->validated('effective_from')}.");
    }

    public function updateLoan(UpdateLoanProductParametersRequest $request, LoanProduct $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('admin.tarif-parameter.index')
            ->with('status', "Parameter produk \"{$product->name}\" berhasil diperbarui.");
    }

    public function addLoanRate(AddLoanRateHistoryRequest $request, LoanProduct $product): RedirectResponse
    {
        $product->rateHistory()->create($request->validated());

        return redirect()
            ->route('admin.tarif-parameter.index')
            ->with('status', "Tarif baru untuk \"{$product->name}\" berlaku sejak {$request->validated('effective_from')}.");
    }
}
