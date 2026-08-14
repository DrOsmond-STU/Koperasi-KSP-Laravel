<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanProductRequest;
use App\Http\Requests\UpdateLoanProductRequest;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.produk-pinjaman.index', [
            'products' => LoanProduct::query()
                ->with([
                    'receivableAccount:id,code,name',
                    'interestIncomeAccount:id,code,name',
                    'provisionIncomeAccount:id,code,name',
                    'penaltyReceivableAccount:id,code,name',
                ])
                ->latest()
                ->get(),
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

    public function edit(LoanProduct $loanProduct): View
    {
        $this->authorize('master_data.update');

        return view('admin.master.produk-pinjaman.edit', [
            'product' => $loanProduct,
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'rate' => $loanProduct->rateAt(),
        ]);
    }

    public function update(UpdateLoanProductRequest $request, LoanProduct $loanProduct): RedirectResponse
    {
        $loanProduct->update($request->validated());

        return redirect()
            ->route('admin.master.loan-products.index')
            ->with('status', "Produk pinjaman \"{$loanProduct->name}\" berhasil diperbarui.");
    }

    /** Lihat SavingsProductController::toggleActive() untuk alasan pemisahannya dari hapus. */
    public function toggleActive(LoanProduct $loanProduct): RedirectResponse
    {
        $this->authorize('master_data.update');

        if (! $loanProduct->is_active && ! $loanProduct->isReadyForActivation()) {
            return back()->with('error', "Produk \"{$loanProduct->name}\" belum dipetakan lengkap ke akun COA — lengkapi dulu sebelum diaktifkan.");
        }

        $loanProduct->update(['is_active' => ! $loanProduct->is_active]);

        return back()->with('status', "Produk \"{$loanProduct->name}\" sekarang ".($loanProduct->is_active ? 'aktif' : 'nonaktif').'.');
    }

    /** Lihat SavingsProductController::destroy() untuk alasan permission dan pagarnya. */
    public function destroy(LoanProduct $loanProduct): RedirectResponse
    {
        $this->authorize('master_data.update');

        if ($loanProduct->loans()->exists()) {
            return back()->with('error', "Produk \"{$loanProduct->name}\" sudah punya pinjaman anggota — nonaktifkan saja, jangan dihapus, supaya riwayatnya tidak hilang.");
        }

        if (DB::table('opening_balance_loans')->where('loan_product_id', $loanProduct->id)->exists()) {
            return back()->with('error', "Produk \"{$loanProduct->name}\" masih dipakai baris Saldo Awal Pinjaman — kosongkan dulu lewat Saldo Awal → Koreksi Data.");
        }

        try {
            $loanProduct->delete();
        } catch (QueryException) {
            return back()->with('error', "Produk \"{$loanProduct->name}\" tidak bisa dihapus karena masih dipakai data lain.");
        }

        return redirect()
            ->route('admin.master.loan-products.index')
            ->with('status', "Produk pinjaman \"{$loanProduct->name}\" berhasil dihapus.");
    }
}
