<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrintSettingsRequest;
use App\Services\Settings\PrintSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Pengaturan Cetakan" — kertas/margin/font/kop yang dipakai bersama oleh
 * SEMUA cetakan baru (prints/layout.blade.php), diatur sekali di sini
 * alih-alih per jenis dokumen. Pola sama seperti BrandingSettingsController.
 */
class PrintSettingsController extends Controller
{
    public function __construct(private readonly PrintSettingsService $printSettings) {}

    public function edit(): View
    {
        $this->authorize('cetakan.manage');

        return view('admin.pengaturan.cetakan', [
            'setting' => $this->printSettings->current(),
        ]);
    }

    public function update(UpdatePrintSettingsRequest $request): RedirectResponse
    {
        $this->printSettings->update([
            ...$request->validated(),
            'show_address' => $request->boolean('show_address'),
        ], $request->user()->id);

        return back()->with('status', 'Pengaturan cetakan berhasil disimpan.');
    }
}
