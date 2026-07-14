<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeTypeRequest;
use App\Models\ChartOfAccount;
use App\Models\FeeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FeeTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.master.jenis-iuran.index', [
            'feeTypes' => FeeType::query()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master.jenis-iuran.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreFeeTypeRequest $request): RedirectResponse
    {
        $feeType = FeeType::query()->create([...$request->validated(), 'is_active' => true]);

        return redirect()->route('admin.master.fee-types.index')
            ->with('status', "Jenis iuran \"{$feeType->name}\" berhasil dibuat.");
    }
}
