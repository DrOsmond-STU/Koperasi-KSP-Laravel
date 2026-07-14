<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Inventory\ReturnException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Models\PosSaleItem;
use App\Services\Inventory\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SalesReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returnService) {}

    public function create(): View
    {
        $this->authorize('pos.create');

        return view('staf.pos-retur', [
            'saleItems' => PosSaleItem::query()
                ->with(['product', 'posSale'])
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function store(StoreSalesReturnRequest $request): RedirectResponse
    {
        $item = PosSaleItem::query()->findOrFail($request->validated('pos_sale_item_id'));

        try {
            $return = $this->returnService->returnSale(
                $item,
                (string) $request->validated('qty'),
                $request->user()->id,
            );
        } catch (ReturnException $exception) {
            return redirect()->route('staf.pos.retur.create')->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('staf.pos.retur.create')
            ->with('status', "Retur penjualan untuk {$item->product->name} sebesar Rp ".number_format((float) $return->unit_price * (float) $return->qty, 0, ',', '.').' berhasil dicatat.');
    }
}
