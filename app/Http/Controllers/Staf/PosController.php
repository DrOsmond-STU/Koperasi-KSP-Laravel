<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Savings\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SavingsAccount;
use App\Services\Pos\PosSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(private readonly PosSaleService $posSaleService) {}

    public function create(): View
    {
        $this->authorize('pos.create');

        $allowed = request()->user()->allowedBranchIds();

        return view('staf.pos', [
            'branches' => $allowed === null
                ? Branch::query()->where('is_active', true)->get()
                : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get(),
            'products' => Product::query()->where('is_active', true)->get(),
            'savingsAccounts' => SavingsAccount::query()->where('status', 'aktif')->with('member')->latest()->limit(50)->get(),
            'recentSales' => PosSale::query()
                ->whereDate('created_at', now()->toDateString())
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(StorePosSaleRequest $request): RedirectResponse
    {
        $savingsAccount = $request->validated('savings_account_id')
            ? SavingsAccount::query()->findOrFail($request->validated('savings_account_id'))
            : null;

        try {
            $sale = $this->posSaleService->sell(
                (int) $request->validated('branch_id'),
                $request->validated('payment_method'),
                $request->validated('items'),
                $request->user()->id,
                $savingsAccount,
            );
        } catch (InsufficientStockException|InsufficientBalanceException $exception) {
            return redirect()->route('staf.pos.create')->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('staf.pos.receipt', $sale)
            ->with('status', "Transaksi {$sale->sale_number} berhasil disimpan.");
    }

    public function receipt(PosSale $sale): View
    {
        $this->authorize('pos.read');

        return view('staf.pos-receipt', [
            'sale' => $sale->load(['items.product', 'savingsAccount.member']),
        ]);
    }
}
