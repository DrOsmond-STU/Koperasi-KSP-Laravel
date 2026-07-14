<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandingRequest;
use App\Services\Settings\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandingSettingsController extends Controller
{
    public function __construct(private readonly BrandingService $branding) {}

    public function edit(): View
    {
        return view('admin.pengaturan.branding', [
            'setting' => $this->branding->current(),
        ]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        $this->branding->update(
            $request->validated(),
            $request->file('logo'),
            $request->user()->id,
        );

        return back()->with('status', 'Pengaturan nama & logo aplikasi berhasil disimpan.');
    }
}
