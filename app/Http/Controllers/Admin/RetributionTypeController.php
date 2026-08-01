<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRetributionTypeRequest;
use App\Http\Requests\UpdateRetributionTypeRequest;
use App\Models\ChartOfAccount;
use App\Models\RetributionType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RetributionTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('master_data.read');

        $types = RetributionType::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.master.jenis-retribusi.index', [
            'types' => $types,
            'activePercentageTotal' => $types->where('is_active', true)->sum('percentage'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('master_data.create');

        return view('admin.master.jenis-retribusi.create', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function store(StoreRetributionTypeRequest $request): RedirectResponse
    {
        $type = RetributionType::query()->create([
            ...$request->validated(),
            'is_active' => false,
        ]);

        return redirect()
            ->route('admin.master.retribution-types.index')
            ->with('status', "Jenis retribusi \"{$type->name}\" berhasil dibuat. Link-kan akun COA lalu aktifkan lewat menu Ubah.");
    }

    public function edit(RetributionType $retributionType): View
    {
        $this->authorize('master_data.read');

        return view('admin.master.jenis-retribusi.edit', [
            'type' => $retributionType,
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }

    public function update(UpdateRetributionTypeRequest $request, RetributionType $retributionType): RedirectResponse
    {
        $retributionType->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.master.retribution-types.index')
            ->with('status', "Jenis retribusi \"{$retributionType->name}\" berhasil diperbarui.");
    }
}
