<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Inventory\StockAdjustmentException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelStockAdjustmentRequest;
use App\Http\Requests\DecideStockAdjustmentRequest;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockReason;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StockAdjustmentController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly StockAdjustmentService $stockAdjustmentService) {}

    public function index(): View
    {
        $this->authorize('persediaan_koreksi.read');

        return view('admin.persediaan.koreksi.index', [
            'pendingAdjustments' => StockAdjustment::query()
                ->where('status', 'menunggu_approval')
                ->with(['product', 'stockReason'])
                ->latest()
                ->get(),
            'postedAdjustments' => StockAdjustment::query()
                ->where('status', 'diposting')
                ->with(['product', 'stockReason'])
                ->latest()
                ->limit(20)
                ->get(),
            'cancelledAdjustments' => StockAdjustment::query()
                ->where('status', 'dibatalkan')
                ->with(['product', 'stockReason'])
                ->latest('cancelled_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('persediaan_koreksi.create');

        $allowed = request()->user()->allowedBranchIds();

        return view('admin.persediaan.koreksi.create', [
            'branches' => $allowed === null
                ? Branch::query()->where('is_active', true)->get()
                : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get(),
            'products' => Product::query()->where('is_active', true)->get(),
            'reasons' => StockReason::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));
        $reason = StockReason::query()->findOrFail($request->validated('stock_reason_id'));

        try {
            $adjustment = $this->stockAdjustmentService->submit(
                $product,
                (int) $request->validated('branch_id'),
                (string) $request->validated('physical_qty'),
                $reason,
                $request->user()->id,
            );
        } catch (StockAdjustmentException $exception) {
            return redirect()->route('admin.persediaan.koreksi.create')->with('error', $exception->getMessage());
        }

        $message = $adjustment->status === 'diposting'
            ? 'Koreksi persediaan (selisih plus) berhasil diposting.'
            : 'Koreksi persediaan (selisih minus) menunggu approval.';

        return redirect()->route('admin.persediaan.koreksi.index')->with('status', $message);
    }

    public function decide(DecideStockAdjustmentRequest $request, StockAdjustment $adjustment): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'setuju') {
                $this->stockAdjustmentService->approve($adjustment, $request->user());
                $message = 'Koreksi persediaan disetujui dan diposting.';
            } else {
                $this->stockAdjustmentService->reject($adjustment, $request->user());
                $message = 'Koreksi persediaan ditolak.';
            }
        } catch (StockAdjustmentException $exception) {
            return redirect()->route('admin.persediaan.koreksi.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.persediaan.koreksi.index')->with('status', $message);
    }

    public function cancel(CancelStockAdjustmentRequest $request, StockAdjustment $adjustment): RedirectResponse
    {
        abort_unless($adjustment->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan koreksi yang Anda buat sendiri.');

        try {
            $this->stockAdjustmentService->reverseAdjustment($adjustment, $request->validated('reason'), $request->user()->id);
        } catch (StockAdjustmentException $exception) {
            return redirect()->route('admin.persediaan.koreksi.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.persediaan.koreksi.index')->with('status', 'Koreksi persediaan berhasil dibatalkan.');
    }

    public function print(StockAdjustment $adjustment): Response
    {
        $this->authorize('persediaan_koreksi.read');

        $pdf = $this->renderPrintPdf('prints.pembelian.koreksi', [
            'adjustment' => $adjustment->load('product', 'stockReason', 'createdBy', 'approvedBy'),
        ]);

        return $pdf->download('koreksi-persediaan-'.$adjustment->id.'.pdf');
    }
}
