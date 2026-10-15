<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddLoanRateHistoryRequest;
use App\Http\Requests\AddSavingsRateHistoryRequest;
use App\Http\Requests\UpdateLoanProductParametersRequest;
use App\Http\Requests\UpdateLoanRateHistoryRequest;
use App\Http\Requests\UpdateSavingsProductParametersRequest;
use App\Http\Requests\UpdateSavingsRateHistoryRequest;
use App\Models\LoanProduct;
use App\Models\LoanProductRateHistory;
use App\Models\SavingsProduct;
use App\Models\SavingsProductRateHistory;
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

    /**
     * Koreksi baris tarif yang SUDAH tersimpan (mis. migrasi 31 Juli 2026
     * yang salah ketik jadi Agustus — laporan staf 26 Agu 2026) — beda dari
     * addSavingsRate() yang menambah kebijakan tarif baru ke depan dan
     * tetap non-retroaktif (E2E-05). Lihat UpdateSavingsRateHistoryRequest.
     */
    public function updateSavingsRate(UpdateSavingsRateHistoryRequest $request, SavingsProduct $product, SavingsProductRateHistory $rate): RedirectResponse
    {
        abort_unless($rate->savings_product_id === $product->id, 404);

        $rate->update($request->validated());

        return redirect()
            ->route('admin.tarif-parameter.index')
            ->with('status', "Tarif \"{$product->name}\" berhasil diperbarui.");
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

    /**
     * Koreksi baris tarif yang SUDAH tersimpan (mis. migrasi 31 Juli 2026
     * yang salah ketik jadi Agustus — laporan staf 26 Agu 2026) — beda dari
     * addLoanRate() yang menambah kebijakan tarif baru ke depan dan tetap
     * non-retroaktif (E2E-05). Lihat UpdateLoanRateHistoryRequest.
     */
    public function updateLoanRate(UpdateLoanRateHistoryRequest $request, LoanProduct $product, LoanProductRateHistory $rate): RedirectResponse
    {
        abort_unless($rate->loan_product_id === $product->id, 404);

        $rate->update($request->validated());

        return redirect()
            ->route('admin.tarif-parameter.index')
            ->with('status', "Tarif \"{$product->name}\" berhasil diperbarui.");
    }
}
