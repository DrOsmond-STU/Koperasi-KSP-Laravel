<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FixedAsset\FixedAssetApprovalException;
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

class FixedAssetController extends Controller
{
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
}
