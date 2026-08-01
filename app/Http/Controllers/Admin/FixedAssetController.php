<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FixedAsset\FixedAssetApprovalException;
use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecideFixedAssetApprovalRequest;
use App\Http\Requests\StoreFixedAssetRequest;
use App\Models\Branch;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Supplier;
use App\Services\FixedAsset\FixedAssetPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FixedAssetController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly FixedAssetPurchaseService $purchaseService) {}

    public function index(): View
    {
        $this->authorize('aktiva_tetap.read');

        return view('admin.aktiva-tetap.index', [
            'pendingAssets' => FixedAsset::query()
                ->where('status', 'menunggu_approval')
                ->with(['category'])
                ->latest()
                ->get(),
            'activeAssets' => FixedAsset::query()
                ->where('status', 'aktif')
                ->with(['category'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('aktiva_tetap.create');

        $allowed = request()->user()->allowedBranchIds();

        return view('admin.aktiva-tetap.create', [
            'branches' => $allowed === null
                ? Branch::query()->where('is_active', true)->get()
                : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get(),
            'categories' => FixedAssetCategory::query()->where('is_active', true)->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(StoreFixedAssetRequest $request): RedirectResponse
    {
        $asset = $this->purchaseService->submit($request->validated(), $request->user()->id);

        $message = $asset->status === 'aktif'
            ? "Aktiva tetap {$asset->name} berhasil diposting."
            : "Aktiva tetap {$asset->name} menunggu approval (di atas ambang nilai).";

        return redirect()->route('admin.aktiva-tetap.index')->with('status', $message);
    }

    public function decide(DecideFixedAssetApprovalRequest $request, FixedAsset $asset): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'setuju') {
                $this->purchaseService->approve($asset, $request->user());
                $message = "Aktiva tetap {$asset->name} disetujui dan diposting.";
            } else {
                $this->purchaseService->reject($asset, $request->user());
                $message = "Aktiva tetap {$asset->name} ditolak.";
            }
        } catch (FixedAssetApprovalException $exception) {
            return redirect()->route('admin.aktiva-tetap.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.aktiva-tetap.index')->with('status', $message);
    }

    public function print(FixedAsset $asset): Response
    {
        $this->authorize('aktiva_tetap.read');

        $pdf = $this->renderPrintPdf('prints.aktiva-tetap.show', [
            'asset' => $asset->load('category', 'supplier', 'createdBy', 'approvedBy'),
        ]);

        return $pdf->download('aktiva-tetap-'.$asset->code.'.pdf');
    }

    public function exportPdf(): Response
    {
        $this->authorize('aktiva_tetap.read');

        $pdf = $this->renderPrintPdf('prints.aktiva-tetap.daftar', [
            'assets' => FixedAsset::query()->with('category')->orderBy('code')->get(),
            'generatedAt' => now(),
        ]);

        return $pdf->download('daftar-aktiva-tetap-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(): BinaryFileResponse
    {
        $this->authorize('aktiva_tetap.read');

        $rows = FixedAsset::query()->with('category')->orderBy('code')->get()->map(fn (FixedAsset $asset) => [
            $asset->code,
            $asset->name,
            $asset->category->name,
            (float) $asset->acquisition_cost,
            $asset->acquisition_date->format('Y-m-d'),
            ucfirst($asset->status),
        ]);

        return Excel::download(
            new GenericListExport(['Kode', 'Nama', 'Kategori', 'Nilai Perolehan', 'Tanggal Perolehan', 'Status'], $rows),
            'daftar-aktiva-tetap-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
