<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Inventory\ReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Models\PurchaseItem;
use App\Models\StockReason;
use App\Services\Inventory\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returnService) {}

    public function create(): View
    {
        $this->authorize('pembelian.create');

        return view('admin.pembelian.retur', [
            'purchaseItems' => PurchaseItem::query()
                ->whereHas('purchaseTransaction', fn ($query) => $query->where('status', 'diposting'))
                ->with(['product', 'purchaseTransaction.supplier'])
                ->latest()
                ->limit(50)
                ->get(),
            'reasons' => StockReason::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $item = PurchaseItem::query()->findOrFail($request->validated('purchase_item_id'));
        $reason = StockReason::query()->findOrFail($request->validated('stock_reason_id'));

        try {
            $return = $this->returnService->returnPurchase(
                $item,
                (string) $request->validated('qty'),
                $reason,
                $request->user()->id,
            );
        } catch (ReturnException $exception) {
            return redirect()->route('admin.pembelian.retur.create')->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.pembelian.retur.create')
            ->with('status', "Retur pembelian untuk {$item->product->name} sebesar Rp ".number_format((float) $return->amount, 0, ',', '.').' berhasil dicatat.');
    }
}
