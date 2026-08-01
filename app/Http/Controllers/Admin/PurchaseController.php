<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Inventory\PurchaseApprovalException;
use App\Exceptions\Inventory\PurchasePaymentException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecidePurchaseApprovalRequest;
use App\Http\Requests\RecordPurchasePaymentRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseTransaction;
use App\Models\Supplier;
use App\Services\Inventory\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PurchaseController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly PurchaseService $purchaseService) {}

    public function index(): View
    {
        $this->authorize('pembelian.read');

        return view('admin.pembelian.index', [
            'pendingPurchases' => PurchaseTransaction::query()
                ->where('status', 'menunggu_approval')
                ->with(['supplier'])
                ->latest()
                ->get(),
            'postedPurchases' => PurchaseTransaction::query()
                ->where('status', 'diposting')
                ->with(['supplier'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('pembelian.create');

        $allowed = request()->user()->allowedBranchIds();

        return view('admin.pembelian.create', [
            'branches' => $allowed === null
                ? Branch::query()->where('is_active', true)->get()
                : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->get(),
            'products' => Product::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->findOrFail($request->validated('supplier_id'));

        $purchase = $this->purchaseService->submit(
            $supplier,
            (int) $request->validated('branch_id'),
            $request->validated('payment_method'),
            $request->validated('items'),
            $request->user()->id,
        );

        $message = $purchase->status === 'diposting'
            ? "Pembelian {$purchase->purchase_number} berhasil diposting."
            : "Pembelian {$purchase->purchase_number} menunggu approval (di atas ambang nilai).";

        return redirect()->route('admin.pembelian.index')->with('status', $message);
    }

    public function decide(DecidePurchaseApprovalRequest $request, PurchaseTransaction $purchase): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'setuju') {
                $this->purchaseService->approve($purchase, $request->user());
                $message = "Pembelian {$purchase->purchase_number} disetujui dan diposting.";
            } else {
                $this->purchaseService->reject($purchase, $request->user());
                $message = "Pembelian {$purchase->purchase_number} ditolak.";
            }
        } catch (PurchaseApprovalException $exception) {
            return redirect()->route('admin.pembelian.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pembelian.index')->with('status', $message);
    }

    public function pay(RecordPurchasePaymentRequest $request, PurchaseTransaction $purchase): RedirectResponse
    {
        try {
            $this->purchaseService->pay($purchase, (string) $request->validated('amount'), $request->user()->id);
        } catch (PurchasePaymentException $exception) {
            return redirect()->route('admin.pembelian.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.pembelian.index')->with('status', "Pembayaran hutang untuk {$purchase->purchase_number} berhasil dicatat.");
    }

    public function print(PurchaseTransaction $purchase): Response
    {
        $this->authorize('pembelian.read');

        $pdf = $this->renderPrintPdf('prints.pembelian.show', [
            'purchase' => $purchase->load('supplier', 'items.product', 'createdBy', 'approvedBy'),
        ]);

        return $pdf->download('pembelian-'.$purchase->purchase_number.'.pdf');
    }
}
