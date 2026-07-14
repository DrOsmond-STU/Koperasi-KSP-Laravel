<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShuAllocationCategoryRequest;
use App\Models\Branch;
use App\Models\ShuAllocationCategory;
use App\Services\Accounting\ShuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShuController extends Controller
{
    public function __construct(private readonly ShuService $shuService) {}

    public function index(Request $request): View
    {
        $this->authorize('shu.read');

        $branchId = $this->resolveBranchId($request);
        $year = $request->integer('year') ?: (int) now()->format('Y');

        return view('admin.shu.index', [
            'categories' => ShuAllocationCategory::query()->get(),
            'simulation' => $this->shuService->simulate($branchId, $year),
            'year' => $year,
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
        ]);
    }

    public function storeCategory(StoreShuAllocationCategoryRequest $request): RedirectResponse
    {
        ShuAllocationCategory::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return redirect()->route('admin.shu.index')->with('status', 'Kategori alokasi SHU berhasil ditambahkan.');
    }

    private function resolveBranchId(Request $request): ?int
    {
        $allowed = $request->user()->allowedBranchIds();
        $requested = $request->integer('branch_id') ?: null;

        if ($allowed === null) {
            return $requested;
        }

        if ($requested !== null && ! in_array($requested, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $requested ?? ($allowed[0] ?? null);
    }

    private function availableBranches(Request $request)
    {
        $allowed = $request->user()->allowedBranchIds();

        return $allowed === null
            ? Branch::query()->where('is_active', true)->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get();
    }
}
