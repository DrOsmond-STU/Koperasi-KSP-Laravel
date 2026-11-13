<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSavingsProductRequest;
use App\Http\Requests\UpdateSavingsProductRequest;
use App\Models\ChartOfAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SavingsProductController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.produk-simpanan.index', [
            'products' => SavingsProduct::query()
                ->with(['liabilityAccount:id,code,name', 'interestExpenseAccount:id,code,name'])
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.produk-simpanan.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'categories' => SavingsProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
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

    public function edit(SavingsProduct $savingsProduct): View
    {
        $this->authorize('master_data.update');

        return view('admin.master.produk-simpanan.edit', [
            'product' => $savingsProduct,
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'categories' => SavingsProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'rate' => $savingsProduct->rateAt(),
        ]);
    }

    public function update(UpdateSavingsProductRequest $request, SavingsProduct $savingsProduct): RedirectResponse
    {
        $savingsProduct->update($request->validated());

        return redirect()
            ->route('admin.master.savings-products.index')
            ->with('status', "Produk simpanan \"{$savingsProduct->name}\" berhasil diperbarui.");
    }

    /**
     * Aktif/nonaktif dipisahkan dari hapus karena itulah jalan keluar yang
     * benar untuk produk yang sudah dipakai: rekeningnya tetap utuh, produknya
     * berhenti muncul di layar transaksi baru.
     */
    public function toggleActive(SavingsProduct $savingsProduct): RedirectResponse
    {
        $this->authorize('master_data.update');

        if (! $savingsProduct->is_active && ! $savingsProduct->isReadyForActivation()) {
            return back()->with('error', "Produk \"{$savingsProduct->name}\" belum dipetakan ke akun COA — lengkapi dulu sebelum diaktifkan.");
        }

        $savingsProduct->update(['is_active' => ! $savingsProduct->is_active]);

        return back()->with('status', "Produk \"{$savingsProduct->name}\" sekarang ".($savingsProduct->is_active ? 'aktif' : 'nonaktif').'.');
    }

    /**
     * Hapus permanen. Dijaga `master_data.update` karena tidak ada permission
     * `master_data.delete` di RolePermissionSeeder — menambahnya berarti
     * mengubah peran di produksi, sementara penghapusan di sini sudah dipagari
     * dua rujukan restrictOnDelete (savings_accounts, opening_balance_savings)
     * yang diperiksa lebih dulu supaya alasannya terbaca, bukan berupa
     * SQLSTATE mentah.
     */
    public function destroy(SavingsProduct $savingsProduct): RedirectResponse
    {
        $this->authorize('master_data.update');

        if ($savingsProduct->accounts()->exists()) {
            return back()->with('error', "Produk \"{$savingsProduct->name}\" sudah punya rekening simpanan anggota — nonaktifkan saja, jangan dihapus, supaya riwayatnya tidak hilang.");
        }

        if (DB::table('opening_balance_savings')->where('savings_product_id', $savingsProduct->id)->exists()) {
            return back()->with('error', "Produk \"{$savingsProduct->name}\" masih dipakai baris Saldo Awal Simpanan — kosongkan dulu lewat Saldo Awal → Koreksi Data.");
        }

        try {
            $savingsProduct->delete();
        } catch (QueryException) {
            return back()->with('error', "Produk \"{$savingsProduct->name}\" tidak bisa dihapus karena masih dipakai data lain.");
        }

        return redirect()
            ->route('admin.master.savings-products.index')
            ->with('status', "Produk simpanan \"{$savingsProduct->name}\" berhasil dihapus.");
    }
}
