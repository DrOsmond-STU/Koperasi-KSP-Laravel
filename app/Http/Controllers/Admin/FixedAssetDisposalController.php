<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FixedAsset\FixedAssetDisposalException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelFixedAssetDisposalRequest;
use App\Http\Requests\StoreFixedAssetDisposalRequest;
use App\Models\FixedAsset;
use App\Models\FixedAssetDisposal;
use App\Services\FixedAsset\FixedAssetDisposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FixedAssetDisposalController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly FixedAssetDisposalService $disposalService) {}

    public function create(): View
    {
        $this->authorize('aktiva_tetap.update');

        return view('admin.aktiva-tetap.pelepasan', [
            'assets' => FixedAsset::query()->where('status', 'aktif')->with('category')->get(),
            'recentDisposals' => FixedAssetDisposal::query()->with('fixedAsset')->latest()->limit(20)->get(),
        ]);
    }

    public function store(StoreFixedAssetDisposalRequest $request): RedirectResponse
    {
        $asset = FixedAsset::query()->findOrFail($request->validated('fixed_asset_id'));

        try {
            $disposal = $this->disposalService->dispose(
                $asset,
                $request->validated('disposal_type'),
                (string) ($request->validated('sale_amount') ?? '0'),
                $request->user()->id,
            );
        } catch (FixedAssetDisposalException $exception) {
            return redirect()->route('admin.aktiva-tetap.pelepasan.create')->with('error', $exception->getMessage());
        }

        $message = $disposal->isGain()
            ? "Pelepasan {$asset->name} dicatat dengan laba Rp ".number_format((float) $disposal->gain_loss_amount, 0, ',', '.').'.'
            : "Pelepasan {$asset->name} dicatat dengan rugi Rp ".number_format(abs((float) $disposal->gain_loss_amount), 0, ',', '.').'.';

        return redirect()->route('admin.aktiva-tetap.pelepasan.create')->with('status', $message);
    }

    public function cancel(CancelFixedAssetDisposalRequest $request, FixedAssetDisposal $disposal): RedirectResponse
    {
        abort_unless($disposal->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan pelepasan yang Anda buat sendiri.');

        try {
            $this->disposalService->reverseDisposal($disposal, $request->validated('reason'), $request->user()->id);
        } catch (FixedAssetDisposalException $exception) {
            return redirect()->route('admin.aktiva-tetap.pelepasan.create')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.aktiva-tetap.pelepasan.create')->with('status', 'Pelepasan aktiva tetap berhasil dibatalkan.');
    }

    public function print(FixedAssetDisposal $disposal): Response
    {
        $this->authorize('aktiva_tetap.read');

        $pdf = $this->renderPrintPdf('prints.aktiva-tetap.pelepasan', [
            'disposal' => $disposal->load('fixedAsset', 'createdBy'),
        ]);

        return $pdf->download('pelepasan-aktiva-tetap-'.$disposal->id.'.pdf');
    }
}
