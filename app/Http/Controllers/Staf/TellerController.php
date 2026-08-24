<?php

namespace App\Http\Controllers\Staf;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelSavingsTransactionRequest;
use App\Http\Requests\DecideSavingsWithdrawalRequest;
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
            'amount' => (float) $request->validated('amount'),
            'description' => $request->validated('description'),
            'lines' => $this->savings->previewLines($account, $request->validated('type'), (float) $request->validated('amount')),
        ]);
    }

    public function store(TellerSavingsTransactionRequest $request): RedirectResponse
    {
        $account = SavingsAccount::query()->findOrFail($request->validated('savings_account_id'));
        $idempotencyKey = $request->input('idempotency_key') ?: (string) Str::uuid();

        $transaction = $request->validated('type') === 'setor'
            ? $this->savings->deposit($account, (float) $request->validated('amount'), $request->user()->id, $request->validated('description'), $idempotencyKey)
            : $this->savings->withdraw($account, (float) $request->validated('amount'), $request->user()->id, $request->validated('description'), $idempotencyKey);

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
}
