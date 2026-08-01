<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\ChartOfAccount;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    use GeneratesPrintPdf;

    public function index(): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.supplier.index', [
            'suppliers' => Supplier::query()->latest()->get(),
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('master_data.read');

        $pdf = $this->renderPrintPdf('prints.master.supplier', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('daftar-supplier-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('master_data.read');

        $rows = Supplier::query()->orderBy('name')->get()->map(fn (Supplier $supplier) => [
            $supplier->code,
            $supplier->name,
            $supplier->payment_term === 'kredit' ? "Kredit {$supplier->payment_term_days} hari" : 'Tunai',
            $supplier->is_active ? 'Aktif' : 'Nonaktif',
        ]);

        return Excel::download(
            new GenericListExport(['Kode', 'Nama', 'Termin Bayar', 'Status'], $rows),
            'daftar-supplier-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.supplier.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.master.suppliers.index')
            ->with('status', "Supplier \"{$supplier->name}\" berhasil dibuat.");
    }
}
