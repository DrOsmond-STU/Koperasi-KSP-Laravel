<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Loans\InvalidLoanApplicationException;
use App\Exceptions\Savings\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\SavingsAccount;
use App\Services\Pos\PosSaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'categories' => Product::query()->where('is_active', true)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'savingsAccounts' => SavingsAccount::query()->where('status', 'aktif')->with('member')->latest()->limit(50)->get(),
            // Cuma produk yang sudah dikonfigurasi tenor harian — lihat
            // catatan yang sama di Staf\LoanApplicationController::create().
            'loanProducts' => LoanProduct::query()->where('is_active', true)->whereNotNull('min_tenor_days')->get(),
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

        $hutang = $request->validated('payment_method') === 'hutang'
            ? [
                'member' => Member::query()->findOrFail($request->validated('member_id')),
                'loan_product' => LoanProduct::query()->findOrFail($request->validated('loan_product_id')),
                'tenor_days' => (int) $request->validated('tenor_days'),
            ]
            : null;

        try {
            $sale = $this->posSaleService->sell(
                (int) $request->validated('branch_id'),
                $request->validated('payment_method'),
                $request->validated('items'),
                $request->user()->id,
                $savingsAccount,
                null,
                $hutang,
            );
        } catch (InsufficientStockException|InsufficientBalanceException|InvalidLoanApplicationException $exception) {
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
            'sale' => $sale->load(['items.product', 'savingsAccount.member', 'loan.member', 'loan.schedules']),
        ]);
    }

    /**
     * Pencarian anggota untuk kasir sentuh — pola search-and-select untuk
     * jumlah anggota yang tidak realistis ditampilkan sebagai <select>
     * statis. Member sudah pakai trait BelongsToBranch, jadi scope cabang
     * otomatis berlaku tanpa kode tambahan di sini.
     */
    public function searchMembers(Request $request): JsonResponse
    {
        $this->authorize('pos.create');

        $q = trim((string) $request->query('q', ''));

        $members = Member::query()
            ->where('status', 'aktif')
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('member_number', 'like', "%{$q}%")
            ))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'member_number', 'name']);

        return response()->json($members->map(fn ($m) => [
            'id' => $m->id,
            'label' => "{$m->member_number} — {$m->name}",
        ]));
    }

    public function searchSavingsAccounts(Request $request): JsonResponse
    {
        $this->authorize('pos.create');

        $q = trim((string) $request->query('q', ''));

        $accounts = SavingsAccount::query()
            ->where('status', 'aktif')
            ->with('member')
            ->when($q !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('account_number', 'like', "%{$q}%")
                    ->orWhereHas('member', fn ($mq) => $mq->where('name', 'like', "%{$q}%"))
            ))
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json($accounts->map(fn ($a) => [
            'id' => $a->id,
            'label' => "{$a->account_number} — {$a->member->name}",
        ]));
    }
}
