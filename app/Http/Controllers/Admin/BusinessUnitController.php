<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessUnitTransactionRequest;
use App\Http\Requests\StoreBusinessUnitRequest;
use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\ChartOfAccount;
use App\Services\BusinessUnit\BusinessUnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BusinessUnitController extends Controller
{
    public function __construct(private readonly BusinessUnitService $businessUnitService) {}

    public function index(): View
    {
        $this->authorize('unit_usaha.read');

        $units = BusinessUnit::query()->where('is_active', true)->get()->map(function (BusinessUnit $unit) {
            $pendapatan = (float) $unit->transactions()->where('type', 'pendapatan')->sum('amount');
            $beban = (float) $unit->transactions()->where('type', 'beban')->sum('amount');

            return [
                'unit' => $unit,
                'pendapatan' => $pendapatan,
                'beban' => $beban,
                'laba_rugi' => round($pendapatan - $beban, 2),
            ];
        });

        return view('admin.unit-usaha.index', [
            'units' => $units,
            'allUnits' => BusinessUnit::query()->where('is_active', true)->get(),
            'branches' => Branch::query()->where('is_active', true)->get(),
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreBusinessUnitRequest $request): RedirectResponse
    {
        $unit = BusinessUnit::query()->create([...$request->validated(), 'is_active' => true]);

        return redirect()->route('admin.unit-usaha.index')
            ->with('status', "Unit usaha \"{$unit->name}\" berhasil dibuat.");
    }

    public function recordTransaction(BusinessUnitTransactionRequest $request): RedirectResponse
    {
        $unit = BusinessUnit::query()->findOrFail($request->validated('business_unit_id'));

        $this->businessUnitService->record(
            $unit,
            $request->validated('type'),
            (float) $request->validated('amount'),
            (int) $request->validated('branch_id'),
            $request->user()->id,
            $request->validated('description'),
        );

        return redirect()->route('admin.unit-usaha.index')
            ->with('status', "Transaksi {$request->validated('type')} unit usaha berhasil dicatat.");
    }
}
