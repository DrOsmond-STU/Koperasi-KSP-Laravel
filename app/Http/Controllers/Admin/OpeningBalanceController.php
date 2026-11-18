<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OpeningBalance\OpeningBalanceLockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportOpeningBalanceRequest;
use App\Http\Requests\StoreOpeningBalanceBatchRequest;
use App\Http\Requests\StoreOpeningBalanceCoaRowRequest;
use App\Http\Requests\StoreOpeningBalanceInstallmentRowRequest;
use App\Http\Requests\StoreOpeningBalanceLoanRowRequest;
use App\Http\Requests\StoreOpeningBalanceSavingRowRequest;
use App\Http\Requests\StoreOpeningBalanceStockRowRequest;
use App\Http\Requests\StoreOpeningBalanceUpfRowRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\Product;
use App\Models\RetributionType;
use App\Models\SavingsProduct;
use App\Services\OpeningBalance\OpeningBalanceImportService;
use App\Services\OpeningBalance\OpeningBalanceLockService;
use App\Services\OpeningBalance\OpeningBalanceReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpeningBalanceController extends Controller
{
    private const SUB_MODULES = ['savings', 'loans', 'installments', 'coa', 'upf', 'stock'];

    public function __construct(
        private readonly OpeningBalanceImportService $importService,
        private readonly OpeningBalanceReconciliationService $reconciliationService,
        private readonly OpeningBalanceLockService $lockService,
    ) {}

    public function index(): View
    {
        $this->authorize('saldo_awal.read');

        return view('admin.saldo-awal.index', [
            'batches' => OpeningBalanceBatch::query()->with('branch')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('saldo_awal.create');

        return view('admin.saldo-awal.create', [
            'branches' => Branch::query()->where('is_active', true)->get(),
        ]);
    }

    public function store(StoreOpeningBalanceBatchRequest $request): RedirectResponse
    {
        $batch = OpeningBalanceBatch::query()->create($request->validated());

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('status', 'Batch migrasi dibuat. Import data per sub-modul di bawah.');
    }

    public function show(OpeningBalanceBatch $batch): View
    {
        $this->authorize('saldo_awal.read');

        return view('admin.saldo-awal.show', [
            'batch' => $batch->load([
                'savings.member', 'savings.savingsProduct',
                'loans.member', 'loans.loanProduct', 'loans.installments',
                'coaLines.account',
                'upf.retributionType',
                'stock.product',
            ]),
            'report' => $this->reconciliationService->reconcile($batch),
            'members' => Member::query()->orderBy('name')->get(['id', 'name', 'member_number']),
            'savingsProducts' => SavingsProduct::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'loanProducts' => LoanProduct::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
            'retributionTypes' => RetributionType::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function import(ImportOpeningBalanceRequest $request, OpeningBalanceBatch $batch, string $subModule): RedirectResponse
    {
        if (! in_array($subModule, self::SUB_MODULES, true) || ! $batch->isDraft()) {
            abort(404);
        }

        $path = $request->file('file')->getRealPath();
        $mode = $request->validated('mode');

        $result = match ($subModule) {
            'savings' => $this->importService->validateSavings($batch, $path),
            'loans' => $this->importService->validateLoans($batch, $path),
            'installments' => $this->importService->validateInstallments($batch, $path),
            'coa' => $this->importService->validateCoa($batch, $path),
            'upf' => $this->importService->validateUpf($batch, $path),
            'stock' => $this->importService->validateStock($batch, $path),
        };

        if ($mode === 'all_or_nothing' && $result->hasErrors()) {
            return redirect()->route('admin.saldo-awal.show', $batch)
                ->with('import_errors', $result->errors)
                ->with('error', "Import {$subModule} dibatalkan — {$this->countLabel($result)} baris bermasalah, tidak ada yang disimpan (mode All-or-nothing).");
        }

        $committed = DB::transaction(fn () => match ($subModule) {
            'savings' => $this->importService->commitSavings($batch, $result->validRows),
            'loans' => $this->importService->commitLoans($batch, $result->validRows),
            'installments' => $this->importService->commitInstallments($batch, $result->validRows),
            'coa' => $this->importService->commitCoa($batch, $result->validRows),
            'upf' => $this->importService->commitUpf($batch, $result->validRows),
            'stock' => $this->importService->commitStock($batch, $result->validRows),
        });

        $message = "Import {$subModule}: {$committed} baris berhasil disimpan";
        $message .= $result->hasErrors() ? ', '.count($result->errors).' baris gagal (lihat rincian).' : '.';

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('import_errors', $result->errors)
            ->with('status', $message);
    }

    /**
     * Input manual satu-per-satu (PRD §9: "Input manual per baris DAN
     * import massal") — 5 method tipis, satu per sub-modul, field & aturan
     * PERSIS mengikuti validator CSV di OpeningBalanceImportService supaya
     * konsisten baik lewat form maupun lewat import.
     */
    public function storeSavingRow(StoreOpeningBalanceSavingRowRequest $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->assertDraft($batch);
        $batch->savings()->create($request->validated());

        return $this->rowStoredResponse($batch);
    }

    public function storeLoanRow(StoreOpeningBalanceLoanRowRequest $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->assertDraft($batch);
        $batch->loans()->create($request->validated());

        return $this->rowStoredResponse($batch);
    }

    public function storeInstallmentRow(StoreOpeningBalanceInstallmentRowRequest $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->assertDraft($batch);
        $batch->installments()->create($request->validated());

        return $this->rowStoredResponse($batch);
    }

    public function storeCoaRow(StoreOpeningBalanceCoaRowRequest $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->assertDraft($batch);
        $batch->coaLines()->create($request->validated());

        return $this->rowStoredResponse($batch);
    }

    public function storeUpfRow(StoreOpeningBalanceUpfRowRequest $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->assertDraft($batch);
        $batch->upf()->create($request->validated());

        return $this->rowStoredResponse($batch);
    }

    public function storeStockRow(StoreOpeningBalanceStockRowRequest $request, OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->assertDraft($batch);
        $batch->stock()->create($request->validated());

        return $this->rowStoredResponse($batch);
    }

    public function destroyRow(OpeningBalanceBatch $batch, string $subModule, int $rowId): RedirectResponse
    {
        $this->authorize('saldo_awal.update');

        if (! in_array($subModule, self::SUB_MODULES, true)) {
            abort(404);
        }
        $this->assertDraft($batch);

        $relation = match ($subModule) {
            'savings' => $batch->savings(),
            'loans' => $batch->loans(),
            'installments' => $batch->installments(),
            'coa' => $batch->coaLines(),
            'upf' => $batch->upf(),
            'stock' => $batch->stock(),
        };

        $relation->findOrFail($rowId)->delete();

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('status', 'Baris berhasil dihapus.');
    }

    private function assertDraft(OpeningBalanceBatch $batch): void
    {
        if (! $batch->isDraft()) {
            abort(404);
        }
    }

    private function rowStoredResponse(OpeningBalanceBatch $batch): RedirectResponse
    {
        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('status', 'Baris berhasil ditambahkan.');
    }

    public function downloadTemplate(string $subModule): StreamedResponse
    {
        $this->authorize('saldo_awal.read');

        if (! in_array($subModule, self::SUB_MODULES, true)) {
            abort(404);
        }

        [$headers, $exampleRow] = match ($subModule) {
            'savings' => [
                ['kode_anggota', 'kode_produk_simpanan', 'no_rekening', 'saldo_awal', 'tanggal_saldo_awal', 'keterangan'],
                ['AGT-0001', 'SIM-SUKARELA', '', '1500000.00', '2026-01-01', 'Migrasi dari sistem lama'],
            ],
            'loans' => [
                ['kode_anggota', 'kode_produk_pinjaman', 'no_pinjaman_lama', 'tanggal_akad', 'plafon_awal', 'sisa_pokok', 'sisa_jasa_berjalan', 'tenor_hari', 'sisa_tenor_hari', 'angsuran_ke', 'tanggal_jatuh_tempo_berikutnya', 'kolektibilitas'],
                ['AGT-0001', 'PINJ-MODAL-USAHA', 'OLD-00234', '2025-06-01', '10000000.00', '6500000.00', '50000.00', '90', '45', '46', '2026-02-01', 'Lancar'],
            ],
            'installments' => [
                ['no_pinjaman_lama', 'angsuran_ke', 'tanggal_jatuh_tempo', 'nominal_pokok', 'nominal_jasa', 'nominal_denda', 'status'],
                ['OLD-00234', '6', '2026-02-01', '900000.00', '50000.00', '0', 'Belum Bayar'],
            ],
            'coa' => [
                ['kode_akun', 'posisi', 'saldo', 'tanggal_saldo_awal'],
                ['1-1000', 'Debit', '50000000.00', '2026-01-01'],
            ],
            'upf' => [
                ['kode_jenis_retribusi', 'saldo_awal', 'keterangan'],
                ['KEB', '250000.00', 'Migrasi dari sistem lama'],
            ],
            'stock' => [
                ['kode_barang', 'qty', 'harga_satuan', 'keterangan'],
                ['BRG-0001', '50.0000', '15000.00', 'Migrasi dari sistem lama'],
            ],
        };

        $filename = "template-saldo-awal-{$subModule}.csv";

        return response()->streamDownload(function () use ($headers, $exampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $exampleRow);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function lock(OpeningBalanceBatch $batch): RedirectResponse
    {
        $this->authorize('saldo_awal.lock');

        try {
            $this->lockService->lock($batch, request()->user());
        } catch (OpeningBalanceLockException $exception) {
            return redirect()->route('admin.saldo-awal.show', $batch)->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.saldo-awal.show', $batch)
            ->with('status', 'Migrasi terkunci — jurnal pembukaan telah dibuat.');
    }

    private function countLabel($result): int
    {
        return count($result->errors);
    }
}
