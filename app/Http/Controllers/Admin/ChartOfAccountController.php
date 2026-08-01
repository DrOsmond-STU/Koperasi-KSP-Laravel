<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChartOfAccountRequest;
use App\Http\Requests\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bagan Akun (Chart of Accounts) admin CRUD — deliberately scoped to
 * admin_sistem only (see RolePermissionSeeder's dedicated `chart_of_account`
 * module), unlike the shared `master_data.*` permission other master-data
 * screens use, since editing this table has app-wide financial-integrity
 * implications every other module's postings depend on.
 */
class ChartOfAccountController extends Controller
{
    use GeneratesPrintPdf;

    public function index(): View
    {
        $this->authorize('chart_of_account.read');

        return view('admin.master.bagan-akun.index', [
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(),
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('chart_of_account.read');

        $pdf = $this->renderPrintPdf('prints.master.bagan-akun', [
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('bagan-akun-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('chart_of_account.read');

        $rows = ChartOfAccount::query()->orderBy('code')->get()->map(fn (ChartOfAccount $account) => [
            $account->code,
            $account->name,
            $account->type,
            $account->group,
            $account->normal_balance,
            $account->is_postable ? 'Ya' : 'Header',
            $account->parent_code,
            $account->statement === 'NERACA' ? 'Neraca' : 'Laba/Rugi',
        ]);

        return Excel::download(
            new GenericListExport(['Kode', 'Nama Akun', 'Tipe', 'Grup', 'Normal', 'Postable', 'Induk', 'Laporan'], $rows),
            'bagan-akun-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function create(): View
    {
        $this->authorize('chart_of_account.create');

        return view('admin.master.bagan-akun.create', [
            'parents' => ChartOfAccount::query()->orderBy('code')->get(),
        ]);
    }

    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $account = ChartOfAccount::query()->create([
            ...$request->validated(),
            'is_postable' => $request->boolean('is_postable'),
        ]);

        return redirect()
            ->route('admin.master.chart-of-accounts.index')
            ->with('status', "Akun \"{$account->code} — {$account->name}\" berhasil dibuat.");
    }

    public function edit(ChartOfAccount $chartOfAccount): View
    {
        $this->authorize('chart_of_account.read');

        return view('admin.master.bagan-akun.edit', [
            'account' => $chartOfAccount,
            'parents' => ChartOfAccount::query()->where('id', '!=', $chartOfAccount->id)->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $oldCode = $chartOfAccount->code;
        $data = [
            ...$request->validated(),
            'is_postable' => $request->boolean('is_postable'),
        ];

        DB::transaction(function () use ($chartOfAccount, $data, $oldCode) {
            $chartOfAccount->update($data);

            // parent_code is a plain indexed string, not a DB foreign key —
            // renaming a code must cascade to any children that pointed at
            // the old code, or they'd silently become orphaned root accounts.
            if ($data['code'] !== $oldCode) {
                ChartOfAccount::query()->where('parent_code', $oldCode)->update(['parent_code' => $data['code']]);
            }
        });

        return redirect()
            ->route('admin.master.chart-of-accounts.index')
            ->with('status', "Akun \"{$chartOfAccount->code} — {$chartOfAccount->name}\" berhasil diperbarui.");
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $this->authorize('chart_of_account.delete');

        if ($chartOfAccount->isProtected()) {
            return back()->with('error', "Akun \"{$chartOfAccount->code}\" adalah akun inti sistem dan tidak bisa dihapus.");
        }

        if ($chartOfAccount->children()->exists()) {
            return back()->with('error', "Akun \"{$chartOfAccount->code}\" masih punya akun anak — pindahkan atau hapus akun anak itu dulu.");
        }

        try {
            $chartOfAccount->delete();
        } catch (QueryException) {
            return back()->with('error', "Akun \"{$chartOfAccount->code}\" tidak bisa dihapus karena masih dipakai di transaksi atau master data lain.");
        }

        return redirect()
            ->route('admin.master.chart-of-accounts.index')
            ->with('status', "Akun \"{$chartOfAccount->code}\" berhasil dihapus.");
    }
}
