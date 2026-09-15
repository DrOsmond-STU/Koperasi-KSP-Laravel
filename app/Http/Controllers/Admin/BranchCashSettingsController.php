<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBranchCashAccountRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Kas per Cabang" — memetakan tiap cabang ke akun kasnya sendiri di Bagan
 * Akun, supaya transaksi yang terikat cabang (mis. angsuran pinjaman lewat
 * Teller — lihat LoanRepaymentService::cashAccount()) posting ke kas
 * cabang yang benar, bukan akun kas konsolidasi `1101` untuk semua cabang.
 *
 * Cabang yang belum dipetakan di sini tetap jatuh ke fallback `1101` di
 * kode masing-masing service — halaman ini murni opsional per cabang.
 */
class BranchCashSettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.update');

        return view('admin.pengaturan.kas-cabang', [
            'branches' => Branch::query()->with('cashAccount')->orderBy('name')->get(),
            // Postable + mengandung "kas" di nama supaya daftar tetap relevan
            // (COA produksi bisa ratusan baris — lihat temuan investigasi
            // 24 Agu 2026: 275 baris chart_of_accounts).
            'cashAccounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('name', 'like', '%kas%')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(UpdateBranchCashAccountRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update(['cash_account_id' => $request->validated('cash_account_id')]);

        return redirect()->route('admin.pengaturan.kas-cabang.index')
            ->with('status', "Akun kas {$branch->name} berhasil diperbarui.");
    }
}
