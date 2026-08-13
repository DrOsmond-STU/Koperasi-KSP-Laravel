<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOpeningBalanceRowRequest;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\OpeningBalanceBatch;
use App\Models\Product;
use App\Models\RetributionType;
use App\Models\SavingsProduct;
use App\Services\OpeningBalance\OpeningBalanceReconciliationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Koreksi Data Saldo Awal — periksa, ubah per baris, dan kosongkan massal
 * selama batch masih draft.
 *
 * Halaman show batch hanya bisa tambah dan hapus satu baris; tidak ada cara
 * membetulkan angka yang salah selain hapus lalu ketik ulang, dan tidak ada
 * cara mengulang import selain bikin batch baru. Modul ini menutup keduanya
 * supaya bendahara bisa memverifikasi angka SEBELUM batch dikunci — setelah
 * dikunci jurnal pembukaan sudah terbit dan koreksi wajib lewat jurnal
 * penyesuaian (RUNBOOK §5.6).
 */
class OpeningBalanceCorrectionController extends Controller
{
    private const SUB_MODULES = [
        'savings' => 'Simpanan',
        'loans' => 'Pinjaman',
        'installments' => 'Angsuran',
        'coa' => 'COA',
        'upf' => 'UPF',
        'stock' => 'Persediaan',
    ];

    public function __construct(
        private readonly OpeningBalanceReconciliationService $reconciliationService,
    ) {}

    public function index(OpeningBalanceBatch $batch): View
    {
        $this->authorize('saldo_awal.read');

        return view('admin.saldo-awal.koreksi', [
            'batch' => $batch,
            'subModules' => self::SUB_MODULES,
            'counts' => collect(self::SUB_MODULES)
                ->map(fn (string $label, string $key) => $this->relation($batch, $key)->count())
                ->all(),
            'report' => $this->reconciliationService->reconcile($batch),
        ]);
    }

    /** Daftar baris satu sub-modul, dengan pencarian. */
    public function rows(Request $request, OpeningBalanceBatch $batch, string $subModule): View
    {
        $this->authorize('saldo_awal.read');
        $this->assertSubModule($subModule);

        $search = trim((string) $request->query('q', ''));

        return view('admin.saldo-awal.koreksi-baris', [
            'batch' => $batch,
            'subModule' => $subModule,
            'label' => self::SUB_MODULES[$subModule],
            'search' => $search,
            'rows' => $this->searchRows($batch, $subModule, $search),
        ]);
    }

    public function editRow(OpeningBalanceBatch $batch, string $subModule, int $rowId): View
    {
        $this->authorize('saldo_awal.read');
        $this->assertSubModule($subModule);

        return view('admin.saldo-awal.koreksi-edit', [
            'batch' => $batch,
            'subModule' => $subModule,
            'label' => self::SUB_MODULES[$subModule],
            'row' => $this->relation($batch, $subModule)->findOrFail($rowId),
            'fields' => $this->fieldsFor($subModule),
            'options' => $this->optionsFor($batch, $subModule),
        ]);
    }

    public function updateRow(UpdateOpeningBalanceRowRequest $request, OpeningBalanceBatch $batch, string $subModule, int $rowId): RedirectResponse
    {
        $this->assertSubModule($subModule);

        if (! $batch->isDraft()) {
            return redirect()
                ->route('admin.saldo-awal.koreksi', $batch)
                ->with('error', 'Batch sudah terkunci — koreksi harus lewat jurnal penyesuaian, bukan dengan mengubah saldo awal.');
        }

        $row = $this->relation($batch, $subModule)->findOrFail($rowId);
        $row->update($request->validated());

        return redirect()
            ->route('admin.saldo-awal.koreksi.rows', [$batch, $subModule])
            ->with('status', 'Baris berhasil diperbarui.');
    }

    public function clear(OpeningBalanceBatch $batch, string $subModule): RedirectResponse
    {
        $this->authorize('saldo_awal.update');
        $this->assertSubModule($subModule);

        if (! $batch->isDraft()) {
            return redirect()
                ->route('admin.saldo-awal.koreksi', $batch)
                ->with('error', 'Batch sudah terkunci — koreksi harus lewat jurnal penyesuaian, bukan dengan mengubah saldo awal.');
        }

        $deleted = DB::transaction(function () use ($batch, $subModule) {
            // Angsuran menunjuk opening_balance_loans; kalau pinjaman dihapus
            // lebih dulu, baris angsuran jadi yatim / melanggar constraint.
            if ($subModule === 'loans') {
                $batch->installments()->delete();
            }

            return $this->relation($batch, $subModule)->delete();
        });

        $label = self::SUB_MODULES[$subModule];
        $pesan = "Data Saldo Awal {$label} dikosongkan — {$deleted} baris dihapus.";

        if ($subModule === 'loans') {
            $pesan .= ' Baris Angsuran ikut dikosongkan karena menempel pada pinjaman.';
        }

        return redirect()
            ->route('admin.saldo-awal.koreksi', $batch)
            ->with('status', $pesan);
    }

    private function assertSubModule(string $subModule): void
    {
        if (! isset(self::SUB_MODULES[$subModule])) {
            abort(404);
        }
    }

    private function relation(OpeningBalanceBatch $batch, string $subModule): HasMany
    {
        return match ($subModule) {
            'savings' => $batch->savings(),
            'loans' => $batch->loans(),
            'installments' => $batch->installments(),
            'coa' => $batch->coaLines(),
            'upf' => $batch->upf(),
            'stock' => $batch->stock(),
        };
    }

    /**
     * Pencarian dibatasi 200 baris — daftar saldo awal bisa ribuan baris dan
     * halaman ini dipakai untuk menelusuri baris tertentu, bukan membaca
     * semuanya sekaligus.
     */
    private function searchRows(OpeningBalanceBatch $batch, string $subModule, string $search)
    {
        $query = $this->relation($batch, $subModule);

        $query = match ($subModule) {
            'savings' => $query->with(['member', 'savingsProduct'])
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('account_number', 'like', "%{$search}%")
                    ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%")))),
            'loans' => $query->with(['member', 'loanProduct'])
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('external_loan_number', 'like', "%{$search}%")
                    ->orWhereHas('member', fn ($m) => $m->where('name', 'like', "%{$search}%")->orWhere('member_number', 'like', "%{$search}%")))),
            'installments' => $query->with('loan')
                ->when($search !== '', fn ($q) => $q->whereHas('loan', fn ($l) => $l->where('external_loan_number', 'like', "%{$search}%"))),
            'coa' => $query->with('account')
                ->when($search !== '', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))),
            'upf' => $query->with('retributionType')
                ->when($search !== '', fn ($q) => $q->whereHas('retributionType', fn ($t) => $t->where('name', 'like', "%{$search}%"))),
            'stock' => $query->with('product')
                ->when($search !== '', fn ($q) => $q->whereHas('product', fn ($p) => $p->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))),
        };

        return $query->orderBy('id')->limit(200)->get();
    }

    /**
     * Definisi field per sub-modul — dipakai satu view edit generik.
     * Nama field WAJIB sama dengan aturan di Store*RowRequest.
     *
     * @return array<string, array{label: string, type: string, options?: string}>
     */
    private function fieldsFor(string $subModule): array
    {
        return match ($subModule) {
            'savings' => [
                'member_id' => ['label' => 'Anggota', 'type' => 'select', 'options' => 'members'],
                'savings_product_id' => ['label' => 'Produk Simpanan', 'type' => 'select', 'options' => 'savingsProducts'],
                'account_number' => ['label' => 'No. Rekening', 'type' => 'text'],
                'balance' => ['label' => 'Saldo Awal', 'type' => 'money'],
                'notes' => ['label' => 'Keterangan', 'type' => 'text'],
            ],
            'loans' => [
                'member_id' => ['label' => 'Anggota', 'type' => 'select', 'options' => 'members'],
                'loan_product_id' => ['label' => 'Produk Pinjaman', 'type' => 'select', 'options' => 'loanProducts'],
                'external_loan_number' => ['label' => 'No. Pinjaman Lama', 'type' => 'text'],
                'disbursement_date' => ['label' => 'Tanggal Akad', 'type' => 'date'],
                'original_principal' => ['label' => 'Plafon Awal', 'type' => 'money'],
                'outstanding_principal' => ['label' => 'Sisa Pokok', 'type' => 'money'],
                'outstanding_interest' => ['label' => 'Sisa Jasa Berjalan', 'type' => 'money'],
                'tenor_months' => ['label' => 'Tenor (bulan)', 'type' => 'number'],
                'remaining_tenor_months' => ['label' => 'Sisa Tenor', 'type' => 'number'],
                'next_installment_number' => ['label' => 'Angsuran Ke', 'type' => 'number'],
                'next_due_date' => ['label' => 'Jatuh Tempo Berikutnya', 'type' => 'date'],
                'collectibility' => ['label' => 'Kolektibilitas', 'type' => 'select', 'options' => 'collectibility'],
            ],
            'installments' => [
                'opening_balance_loan_id' => ['label' => 'Pinjaman', 'type' => 'select', 'options' => 'batchLoans'],
                'installment_number' => ['label' => 'Angsuran Ke', 'type' => 'number'],
                'due_date' => ['label' => 'Jatuh Tempo', 'type' => 'date'],
                'principal_amount' => ['label' => 'Nominal Pokok', 'type' => 'money'],
                'interest_amount' => ['label' => 'Nominal Jasa', 'type' => 'money'],
                'penalty_amount' => ['label' => 'Nominal Denda', 'type' => 'money'],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => 'installmentStatus'],
            ],
            'coa' => [
                'chart_of_account_id' => ['label' => 'Akun', 'type' => 'select', 'options' => 'accounts'],
                'position' => ['label' => 'Posisi', 'type' => 'select', 'options' => 'position'],
                'amount' => ['label' => 'Saldo', 'type' => 'money'],
            ],
            'upf' => [
                'retribution_type_id' => ['label' => 'Jenis Retribusi', 'type' => 'select', 'options' => 'retributionTypes'],
                'amount' => ['label' => 'Saldo Awal', 'type' => 'money'],
                'notes' => ['label' => 'Keterangan', 'type' => 'text'],
            ],
            'stock' => [
                'product_id' => ['label' => 'Barang', 'type' => 'select', 'options' => 'products'],
                'qty' => ['label' => 'Qty', 'type' => 'money'],
                'unit_cost' => ['label' => 'Harga Satuan', 'type' => 'money'],
                'notes' => ['label' => 'Keterangan', 'type' => 'text'],
            ],
        };
    }

    /** @return array<string, array<int|string, string>> */
    private function optionsFor(OpeningBalanceBatch $batch, string $subModule): array
    {
        return match ($subModule) {
            'savings' => [
                'members' => $this->memberOptions(),
                'savingsProducts' => SavingsProduct::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
            'loans' => [
                'members' => $this->memberOptions(),
                'loanProducts' => LoanProduct::query()->orderBy('name')->pluck('name', 'id')->all(),
                'collectibility' => [
                    'lancar' => 'Lancar',
                    'kurang_lancar' => 'Kurang Lancar',
                    'diragukan' => 'Diragukan',
                    'macet' => 'Macet',
                ],
            ],
            'installments' => [
                'batchLoans' => $batch->loans()->with('member')->get()
                    ->mapWithKeys(fn (Model $loan) => [
                        $loan->id => ($loan->external_loan_number ?: 'ID '.$loan->id).' — '.($loan->member->name ?? '-'),
                    ])->all(),
                'installmentStatus' => ['belum_bayar' => 'Belum Bayar', 'sebagian' => 'Sebagian'],
            ],
            'coa' => [
                'accounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get()
                    ->mapWithKeys(fn (Model $a) => [$a->id => $a->code.' — '.$a->name])->all(),
                'position' => ['debit' => 'Debit', 'kredit' => 'Kredit'],
            ],
            'upf' => [
                'retributionTypes' => RetributionType::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
            'stock' => [
                'products' => Product::query()->orderBy('name')->get()
                    ->mapWithKeys(fn (Model $p) => [$p->id => $p->code.' — '.$p->name])->all(),
            ],
        };
    }

    /** @return array<int, string> */
    private function memberOptions(): array
    {
        return Member::query()->orderBy('name')->get(['id', 'name', 'member_number'])
            ->mapWithKeys(fn (Member $m) => [$m->id => $m->member_number.' — '.$m->name])
            ->all();
    }
}
