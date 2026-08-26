<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Savings\InsufficientBalanceException;
use App\Exceptions\Savings\TransactionAlreadyCancelledException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelSavingsTransactionRequest;
use App\Http\Requests\DecideSavingsWithdrawalRequest;
use App\Http\Requests\EditSavingsTransactionRequest;
use App\Http\Requests\OpenSavingsAccountRequest;
use App\Http\Requests\TellerSavingsTransactionRequest;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\SavingsWithdrawalRequest;
use App\Services\Savings\SavingsService;
use App\Services\Savings\SavingsWithdrawalRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TellerController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(
        private readonly SavingsService $savings,
        private readonly SavingsWithdrawalRequestService $withdrawalRequests,
    ) {}

    public function create(): View
    {
        $this->authorize('simpanan.create');

        return view('staf.teller', [
            // Regresi: sebelumnya dibatasi ->latest()->limit(50), sehingga
            // dari ~1.200+ rekening aktif produksi hanya 50 yang paling
            // baru dibuat yang muncul — mayoritas rekening Simpanan Pokok/
            // Wajib/Sukarela anggota lama hilang dari dropdown. Select ini
            // sudah pakai enhancer .js-searchable (client-side filter),
            // jadi memuat semua rekening aktif dan diurutkan per nama
            // anggota supaya tetap gampang dicari.
            'accounts' => SavingsAccount::query()->where('status', 'aktif')->with(['member', 'savingsProduct'])->get()
                ->sortBy(fn ($account) => $account->member?->name)
                ->values(),
            'recentTransactions' => SavingsTransaction::query()
                ->whereDate('created_at', now()->toDateString())
                ->with(['savingsAccount.member'])
                ->latest()
                ->limit(20)
                ->get(),
            'pendingWithdrawals' => SavingsWithdrawalRequest::query()
                ->where('status', 'menunggu')
                ->with(['savingsAccount', 'member'])
                ->latest()
                ->get(),
            'cashAccount' => $this->savings->cashAccount(),
        ]);
    }

    /**
     * Shows the Journal Preview (DESIGN §Transaction Panel) before the
     * Teller confirms the transaction.
     */
    public function preview(TellerSavingsTransactionRequest $request): View
    {
        $account = SavingsAccount::query()->findOrFail($request->validated('savings_account_id'));

        return view('staf.teller-preview', [
            'account' => $account,
            'type' => $request->validated('type'),
            'transactionDate' => $request->validated('transaction_date'),
            'amount' => (float) $request->validated('amount'),
            'description' => $request->validated('description'),
            'lines' => $this->savings->previewLines($account, $request->validated('type'), (float) $request->validated('amount')),
        ]);
    }

    public function store(TellerSavingsTransactionRequest $request): RedirectResponse
    {
        $account = SavingsAccount::query()->findOrFail($request->validated('savings_account_id'));
        $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();
        $date = Carbon::parse($request->validated('transaction_date'));

        $transaction = $request->validated('type') === 'setor'
            ? $this->savings->deposit($account, (float) $request->validated('amount'), $request->user()->id, $request->validated('description'), $idempotencyKey, $date)
            : $this->savings->withdraw($account, (float) $request->validated('amount'), $request->user()->id, $request->validated('description'), $idempotencyKey, $date);

        return redirect()
            ->route('staf.teller.create')
            ->with('status', "Transaksi {$request->validated('type')} Rp ".number_format($transaction->amount, 0, ',', '.').' berhasil disimpan.');
    }

    public function cancel(CancelSavingsTransactionRequest $request, SavingsTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan transaksi yang Anda buat sendiri.');

        $this->savings->reverseTransaction($transaction, $request->validated('reason'), $request->user()->id);

        return redirect()
            ->route('staf.teller.create')
            ->with('status', "Transaksi {$transaction->id} berhasil dibatalkan.");
    }

    /**
     * Form Edit — laporan staf 26 Agu 2026: koreksi transaksi Setor/Tarik
     * yang salah catat langsung dari halaman Riwayat. Ledger append-only —
     * lihat SavingsService::editTransaction().
     */
    public function editForm(SavingsTransaction $transaction): View
    {
        abort_unless($transaction->canBeCancelledBy(request()->user()), 403, 'Anda hanya bisa mengedit transaksi yang Anda buat sendiri.');
        abort_if($transaction->isCancelled(), 422, 'Transaksi yang sudah dibatalkan tidak bisa diedit.');

        return view('staf.teller-edit', [
            'transaction' => $transaction->load('savingsAccount.member'),
        ]);
    }

    public function update(EditSavingsTransactionRequest $request, SavingsTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa mengedit transaksi yang Anda buat sendiri.');

        try {
            $this->savings->editTransaction(
                $transaction,
                $request->validated('type'),
                (float) $request->validated('amount'),
                $request->validated('description'),
                Carbon::parse($request->validated('transaction_date')),
                $request->validated('reason'),
                $request->user()->id,
            );
        } catch (TransactionAlreadyCancelledException $exception) {
            // Bukan balik ke form edit (editForm() akan 422 kalau transaksi
            // ini sudah dibatalkan duluan, mis. dua tab dibuka bersamaan) —
            // langsung ke Riwayat supaya pesannya kebaca.
            return redirect()->route('staf.teller.history')->with('error', $exception->getMessage());
        } catch (InsufficientBalanceException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('staf.teller.history')
            ->with('status', "Transaksi {$transaction->id} berhasil diedit.");
    }

    /**
     * Bukti Kas Keluar (tarik) / Kas Masuk (setor) — setiap transaksi teller
     * bisa dicetak ulang kapan saja, bukan hanya sekali saat dibuat.
     */
    public function printReceipt(SavingsTransaction $transaction): Response
    {
        $this->authorize('simpanan.print');

        $pdf = $this->renderPrintPdf('prints.savings.receipt', [
            'transaction' => $transaction->load('savingsAccount.member', 'createdBy'),
            'documentGroup' => $transaction->type === 'tarik' ? 'kas_keluar' : 'kas_masuk',
        ]);

        return $pdf->download('bukti-'.$transaction->type.'-'.$transaction->id.'.pdf');
    }

    /**
     * Riwayat Transaksi Simpanan — laporan staf 26 Agu 2026: panel
     * "Transaksi Hari Ini" di halaman utama Teller cuma menampilkan
     * transaksi HARI INI (limit 20). Halaman terpisah ini menampilkan
     * SEMUA transaksi (semua tanggal, semua cabang), dengan pencarian +
     * filter + paginasi — sama pola dengan tab "Transaksi" pada
     * RetributionController (filteredTransactions()/transactionFilters()).
     *
     * Baca (detail/cetak) dan Batalkan dipakai ulang persis dari panel
     * utama — "Edit" di sini = Batalkan lalu catat ulang lewat form Setor/
     * Tarik biasa (ledger append-only, lihat SavingsService::
     * reverseTransaction()), bukan mengubah baris yang sudah diposting.
     */
    public function history(Request $request): View
    {
        $this->authorize('simpanan.read');

        $filters = $this->transactionFilters($request);

        return view('staf.teller-riwayat', [
            'transactions' => $this->filteredTransactions($filters),
            'filters' => $filters,
        ]);
    }

    public function decideWithdrawal(DecideSavingsWithdrawalRequest $request, SavingsWithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        if ($request->validated('decision') === 'setuju') {
            $this->withdrawalRequests->approve($withdrawalRequest, $request->user()->id);
            $message = "Pengajuan penarikan #{$withdrawalRequest->id} disetujui dan diproses.";
        } else {
            $this->withdrawalRequests->reject($withdrawalRequest, $request->user()->id, $request->validated('notes'));
            $message = "Pengajuan penarikan #{$withdrawalRequest->id} ditolak.";
        }

        return redirect()
            ->route('staf.teller.create')
            ->with('status', $message);
    }

    /**
     * Buka Rekening Simpanan (Setoran Awal) — jalur satu-satunya untuk
     * anggota yang BELUM punya rekening simpanan sama sekali. Tanpa ini,
     * dropdown "Rekening" di create() di atas cuma memuat rekening yang
     * sudah ada, jadi anggota baru tidak bisa disetorkan apapun.
     *
     * Bisa buka beberapa produk sekaligus (mis. Pokok + Wajib bersamaan
     * saat pendaftaran) — setiap produk boleh langsung disertai setoran
     * awal, diposting sebagai transaksi "setor" biasa lewat
     * SavingsService::openAccount() (jurnal tetap konsisten).
     */
    public function createAccount(): View
    {
        $this->authorize('simpanan.create');

        return view('staf.teller-buka-rekening', [
            'members' => Member::query()
                ->whereIn('status', ['calon', 'aktif', 'nonaktif'])
                ->orderBy('name')
                ->get(),
            'products' => SavingsProduct::query()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'cashAccount' => $this->savings->cashAccount(),
        ]);
    }

    public function storeAccount(OpenSavingsAccountRequest $request): RedirectResponse
    {
        $member = Member::query()->findOrFail($request->validated('member_id'));
        $productIds = $request->validated('product_ids');
        $deposits = (array) $request->input('initial_deposits', []);

        $opened = [];
        $skipped = [];

        DB::transaction(function () use ($member, $productIds, $deposits, $request, &$opened, &$skipped) {
            foreach ($productIds as $productId) {
                $product = SavingsProduct::query()->findOrFail($productId);

                // Cegah rekening dobel untuk produk yang sama — anggota
                // boleh punya banyak rekening Sukarela kalau produknya
                // memang berbeda, tapi tidak dua rekening aktif dari
                // produk yang identik.
                $alreadyActive = SavingsAccount::query()
                    ->where('member_id', $member->id)
                    ->where('savings_product_id', $product->id)
                    ->where('status', 'aktif')
                    ->exists();

                if ($alreadyActive) {
                    $skipped[] = $product->name;

                    continue;
                }

                $amount = (float) ($deposits[$productId] ?? 0);
                $account = $this->savings->openAccount($member, $product, $member->branch_id, $amount, $request->user()->id);
                $opened[] = "{$account->account_number} ({$product->name})";
            }
        });

        if (empty($opened)) {
            return redirect()->route('staf.teller.buka-rekening.create')
                ->with('error', 'Tidak ada rekening baru dibuka — '.$member->name.' sudah punya rekening aktif untuk seluruh produk yang dipilih.');
        }

        $status = "Rekening berhasil dibuka untuk {$member->name}: ".implode(', ', $opened).'.';
        if (! empty($skipped)) {
            $status .= ' Dilewati (sudah punya rekening aktif): '.implode(', ', $skipped).'.';
        }

        return redirect()->route('staf.teller.create')->with('status', $status);
    }

    /**
     * @return array{q: ?string, type: ?string, status: ?string, date_from: ?string, date_to: ?string}
     */
    private function transactionFilters(Request $request): array
    {
        return [
            'q' => $request->string('q')->trim()->value() ?: null,
            'type' => $request->string('type')->value() ?: null,
            'status' => $request->string('status')->value() ?: null,
            'date_from' => $request->string('date_from')->value() ?: null,
            'date_to' => $request->string('date_to')->value() ?: null,
        ];
    }

    /**
     * Difilter/diurutkan lewat COALESCE(transaction_date, DATE(created_at))
     * — baris lama (sebelum kolom transaction_date ada) tetap ikut
     * terfilter/terurut lewat tanggal dibuatnya, bukan hilang dari hasil
     * pencarian (sama fallback tampilan dengan SavingsTransaction::
     * transactionOn()).
     */
    private function filteredTransactions(array $filters): LengthAwarePaginator
    {
        return SavingsTransaction::query()
            ->when($filters['q'], fn ($q, $search) => $q->where(
                fn ($w) => $w
                    ->whereHas('savingsAccount', fn ($sa) => $sa
                        ->where('account_number', 'like', "%{$search}%")
                        ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$search}%")))
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['date_from'], fn ($q, $date) => $q->whereRaw('COALESCE(transaction_date, DATE(created_at)) >= ?', [$date]))
            ->when($filters['date_to'], fn ($q, $date) => $q->whereRaw('COALESCE(transaction_date, DATE(created_at)) <= ?', [$date]))
            ->when($filters['status'] === 'aktif', fn ($q) => $q->whereNull('cancelled_at'))
            ->when($filters['status'] === 'dibatalkan', fn ($q) => $q->whereNotNull('cancelled_at'))
            ->with(['savingsAccount.member'])
            ->orderByRaw('COALESCE(transaction_date, DATE(created_at)) DESC')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
    }
}
