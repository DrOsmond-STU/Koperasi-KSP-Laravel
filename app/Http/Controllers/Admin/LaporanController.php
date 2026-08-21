<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericListExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\BusinessUnitTransaction;
use App\Models\ChartOfAccount;
use App\Models\CooperativeEvent;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\FixedAssetDisposal;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseTransaction;
use App\Models\RetributionTransaction;
use App\Models\RetributionType;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\StockAdjustment;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\TellerCashTransaction;
use App\Models\User;
use App\Services\Inventory\InventoryReportService;
use App\Services\Reporting\LaporanRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consolidated "Laporan" hub — one page per form/fitur that has data, all
 * displayed as a searchable/filterable datatable with PDF+Excel export.
 * fetch() is the single dispatch point; each private per-module method
 * returns rows already formatted for display (money/date/status as ready-
 * to-print strings) so the same array feeds the web table, the PDF view,
 * and the Excel export identically — no per-surface reformatting.
 *
 * Row-level branch scoping is NOT done manually here for most modules: every
 * transactional model already applies BranchScope as a global scope keyed
 * off the authenticated user (see App\Models\Scopes\BranchScope), so a plain
 * ::query() is already restricted. CooperativeEvent is the one exception
 * (its own multi-branch/role scoping shape, no BranchScope trait), so it is
 * filtered manually here exactly like CalendarService does.
 */
class LaporanController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly InventoryReportService $inventoryReportService) {}

    public function index(): View
    {
        $this->authorize('laporan.read');

        return view('admin.laporan.index', [
            'groups' => LaporanRegistry::groups(),
        ]);
    }

    public function show(string $module): View
    {
        $this->authorize('laporan.read');
        abort_unless(LaporanRegistry::exists($module), 404);

        return view('admin.laporan.show', [
            'module' => $module,
            'label' => LaporanRegistry::labelFor($module),
            'columns' => LaporanRegistry::columnsFor($module),
            'filterable' => LaporanRegistry::filterableFor($module),
            'dateColumn' => LaporanRegistry::dateColumnFor($module),
            'rows' => $this->fetch($module),
        ]);
    }

    public function exportPdf(string $module): Response
    {
        $this->authorize('laporan.read');
        abort_unless(LaporanRegistry::exists($module), 404);

        $pdf = $this->renderPrintPdf('prints.laporan.generic', [
            'title' => LaporanRegistry::labelFor($module),
            'columns' => LaporanRegistry::columnsFor($module),
            'rows' => $this->fetch($module),
            'generatedAt' => now(),
        ]);

        return $pdf->download(Str::slug($module).'-'.now()->format('Ymd-His').'.pdf');
    }

    public function exportExcel(string $module): BinaryFileResponse
    {
        $this->authorize('laporan.read');
        abort_unless(LaporanRegistry::exists($module), 404);

        $columns = LaporanRegistry::columnsFor($module);
        $rows = $this->fetch($module)->map(
            fn (array $row) => array_map(fn (string $key) => $row[$key] ?? '-', array_keys($columns))
        );

        return Excel::download(
            new GenericListExport(array_values($columns), $rows),
            Str::slug($module).'-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetch(string $module): Collection
    {
        return match ($module) {
            'anggota' => $this->anggota(),
            'jenis_anggota' => $this->jenisAnggota(),
            'bagan_akun' => $this->baganAkun(),
            'simpanan_rekening' => $this->simpananRekening(),
            'simpanan_transaksi' => $this->simpananTransaksi(),
            'pinjaman' => $this->pinjaman(),
            'angsuran' => $this->angsuran(),
            'transaksi_pinjaman' => $this->transaksiPinjaman(),
            'pembayaran_angsuran' => $this->pembayaranAngsuran(),
            'pengajuan_persetujuan_pinjaman' => $this->pengajuanPersetujuanPinjaman(),
            'barang' => $this->barang(),
            'supplier' => $this->supplier(),
            'pembelian' => $this->pembelian(),
            'retur_pembelian' => $this->returPembelian(),
            'koreksi_persediaan' => $this->koreksiPersediaan(),
            'persediaan_kartu' => $this->persediaanKartu(),
            'saldo_persediaan' => $this->saldoPersediaan(),
            'kategori_aktiva_tetap' => $this->kategoriAktivaTetap(),
            'aktiva_tetap' => $this->aktivaTetap(),
            'pelepasan_aktiva_tetap' => $this->pelepasanAktivaTetap(),
            'jenis_retribusi' => $this->jenisRetribusi(),
            'retribusi_upf' => $this->retribusiUpf(),
            'unit_usaha' => $this->unitUsaha(),
            'transaksi_unit_usaha' => $this->transaksiUnitUsaha(),
            'transaksi_pos' => $this->transaksiPos(),
            'transaksi_teller' => $this->transaksiTeller(),
            'kalender_kegiatan' => $this->kalenderKegiatan(),
            'jurnal_umum' => $this->jurnalUmum(),
            'pengguna' => $this->pengguna(),
            default => collect(),
        };
    }

    private function rupiah(float|string $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function anggota(): Collection
    {
        return Member::query()
            ->with(['memberType', 'branch'])
            ->orderBy('name')
            ->get()
            ->map(fn (Member $member) => [
                'member_number' => $member->member_number,
                'name' => $member->name,
                'jenis' => $member->memberType->name ?? '-',
                'cabang' => $member->branch->name ?? '-',
                'status' => ucfirst($member->status),
                'joined_at' => optional($member->joined_at)->format('d-m-Y') ?? '-',
            ]);
    }

    private function jenisAnggota(): Collection
    {
        return MemberType::query()->orderBy('name')->get()->map(fn (MemberType $type) => [
            'code' => $type->code,
            'name' => $type->name,
            'hak_suara' => $type->has_voting_rights ? 'Ya' : 'Tidak',
            'wajib_simpanan' => $type->requires_mandatory_savings ? 'Ya' : 'Tidak',
            'nominal_wajib' => $this->rupiah((float) $type->mandatory_savings_default_amount),
            'status' => $type->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function baganAkun(): Collection
    {
        return ChartOfAccount::query()->orderBy('code')->get()->map(fn (ChartOfAccount $account) => [
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'group' => $account->group,
            'normal_balance' => ucfirst($account->normal_balance),
            'is_postable' => $account->is_postable ? 'Ya' : 'Tidak',
        ]);
    }

    private function simpananRekening(): Collection
    {
        return SavingsAccount::query()
            ->with(['member', 'savingsProduct', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (SavingsAccount $account) => [
                'account_number' => $account->account_number,
                'member_name' => $account->member->name ?? '-',
                'produk' => $account->savingsProduct->name ?? '-',
                'cabang' => $account->branch->name ?? '-',
                'balance' => $this->rupiah((float) $account->balance),
                'status' => ucfirst($account->status),
                'opened_at' => optional($account->opened_at)->format('d-m-Y') ?? '-',
            ]);
    }

    private function simpananTransaksi(): Collection
    {
        return SavingsTransaction::query()
            ->with(['savingsAccount.member', 'branch'])
            ->latest('id')
            ->get()
            ->map(fn (SavingsTransaction $trx) => [
                'tanggal' => $trx->created_at->format('d-m-Y H:i'),
                'account_number' => $trx->savingsAccount->account_number ?? '-',
                'member_name' => $trx->savingsAccount->member->name ?? '-',
                'jenis' => ucfirst($trx->type),
                'amount' => $this->rupiah((float) $trx->amount),
                'balance_after' => $this->rupiah((float) $trx->balance_after),
                'cabang' => $trx->branch->name ?? '-',
                'status' => $trx->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function pinjaman(): Collection
    {
        return Loan::query()
            ->with(['member', 'loanProduct', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Loan $loan) => [
                'loan_number' => $loan->loan_number,
                'member_name' => $loan->member->name ?? '-',
                'produk' => $loan->loanProduct->name ?? '-',
                'cabang' => $loan->branch->name ?? '-',
                'principal_amount' => $this->rupiah((float) $loan->principal_amount),
                'tenor_days' => (string) $loan->tenor_days,
                'status' => ucfirst($loan->status),
                'collectibility' => (string) $loan->collectibility,
                'disbursed_at' => optional($loan->disbursed_at)->format('d-m-Y') ?? '-',
            ]);
    }

    private function angsuran(): Collection
    {
        return LoanSchedule::query()
            ->whereHas('loan')
            ->with(['loan.member'])
            ->orderByDesc('due_date')
            ->get()
            ->map(fn (LoanSchedule $schedule) => [
                'loan_number' => $schedule->loan->loan_number ?? '-',
                'member_name' => $schedule->loan->member->name ?? '-',
                'installment_number' => (string) $schedule->installment_number,
                'due_date' => $schedule->due_date->format('d-m-Y'),
                'total_amount' => $this->rupiah((float) $schedule->total_amount),
                'paid_amount' => $this->rupiah((float) $schedule->paid_amount),
                'status' => ucfirst($schedule->status),
            ]);
    }

    private function transaksiPinjaman(): Collection
    {
        return Loan::query()
            ->whereNotNull('disbursed_at')
            ->with(['member', 'loanProduct', 'branch'])
            ->orderByDesc('disbursed_at')
            ->get()
            ->map(fn (Loan $loan) => [
                'loan_number' => $loan->loan_number,
                'member_name' => $loan->member->name ?? '-',
                'produk' => $loan->loanProduct->name ?? '-',
                'cabang' => $loan->branch->name ?? '-',
                'tanggal_cair' => $loan->disbursed_at->format('d-m-Y'),
                'jumlah' => $this->rupiah((float) $loan->principal_amount),
            ]);
    }

    private function pembayaranAngsuran(): Collection
    {
        return LoanRepayment::query()
            ->with(['loan.member', 'branch'])
            ->latest('id')
            ->get()
            ->map(fn (LoanRepayment $repayment) => [
                'tanggal' => $repayment->created_at->format('d-m-Y H:i'),
                'loan_number' => $repayment->loan->loan_number ?? '-',
                'member_name' => $repayment->loan->member->name ?? '-',
                'cabang' => $repayment->branch->name ?? '-',
                'jumlah' => $this->rupiah((float) $repayment->amount),
                'pokok' => $this->rupiah((float) $repayment->principal_portion),
                'jasa' => $this->rupiah((float) $repayment->interest_portion),
                'saldo_akhir' => $this->rupiah((float) $repayment->balance_after),
                'status' => $repayment->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function pengajuanPersetujuanPinjaman(): Collection
    {
        $decisionLabels = ['setuju' => 'Disetujui', 'tolak' => 'Ditolak'];

        return LoanApproval::query()
            ->whereHas('loan')
            ->with(['loan.member', 'approvedBy'])
            ->latest('decided_at')
            ->get()
            ->map(fn (LoanApproval $approval) => [
                'tanggal' => optional($approval->decided_at)->format('d-m-Y H:i') ?? '-',
                'loan_number' => $approval->loan->loan_number ?? '-',
                'member_name' => $approval->loan->member->name ?? '-',
                'penyetuju' => $approval->approvedBy->name ?? '-',
                'keputusan' => $decisionLabels[$approval->decision] ?? ucfirst($approval->decision),
                'catatan' => $approval->notes ?: '-',
            ]);
    }

    private function barang(): Collection
    {
        return Product::query()->orderBy('name')->get()->map(fn (Product $product) => [
            'code' => $product->code,
            'name' => $product->name,
            'category' => $product->category ?: '-',
            'unit' => $product->unit,
            'purchase_price' => $this->rupiah((float) $product->purchase_price),
            'selling_price' => $this->rupiah((float) $product->selling_price),
            'status' => $product->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function supplier(): Collection
    {
        return Supplier::query()->orderBy('name')->get()->map(fn (Supplier $supplier) => [
            'code' => $supplier->code,
            'name' => $supplier->name,
            'type' => ucfirst($supplier->type),
            'payment_term' => $supplier->isKredit() ? "Kredit {$supplier->payment_term_days} hari" : 'Tunai',
            'status' => $supplier->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function pembelian(): Collection
    {
        return PurchaseTransaction::query()
            ->with(['supplier', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseTransaction $purchase) => [
                'purchase_number' => $purchase->purchase_number,
                'tanggal' => $purchase->purchase_date->format('d-m-Y'),
                'supplier_name' => $purchase->supplier->name ?? '-',
                'cabang' => $purchase->branch->name ?? '-',
                'total_amount' => $this->rupiah((float) $purchase->total_amount),
                'payment_method' => ucfirst($purchase->payment_method),
                'status' => ucfirst($purchase->status),
                'payment_status' => ucfirst($purchase->payment_status),
            ]);
    }

    private function returPembelian(): Collection
    {
        return PurchaseReturn::query()
            ->with(['purchaseItem.product', 'purchaseItem.purchaseTransaction.supplier', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchaseReturn $return) => [
                'tanggal' => $return->return_date->format('d-m-Y'),
                'no_pembelian' => $return->purchaseItem->purchaseTransaction->purchase_number ?? '-',
                'barang' => $return->purchaseItem->product->name ?? '-',
                'supplier_name' => $return->purchaseItem->purchaseTransaction->supplier->name ?? '-',
                'qty' => (string) $return->qty,
                'amount' => $this->rupiah((float) $return->amount),
                'cabang' => $return->branch->name ?? '-',
            ]);
    }

    private function koreksiPersediaan(): Collection
    {
        return StockAdjustment::query()
            ->with(['product', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockAdjustment $adjustment) => [
                'tanggal' => $adjustment->adjustment_date->format('d-m-Y'),
                'barang' => $adjustment->product->name ?? '-',
                'cabang' => $adjustment->branch->name ?? '-',
                'system_qty' => (string) $adjustment->system_qty,
                'physical_qty' => (string) $adjustment->physical_qty,
                'variance_qty' => (string) $adjustment->variance_qty,
                'amount' => $this->rupiah((float) $adjustment->amount),
                'status' => $adjustment->isCancelled() ? 'Dibatalkan' : ucfirst($adjustment->status),
            ]);
    }

    /**
     * Format kartu buku besar: dikelompokkan per barang+cabang (baris
     * dalam satu grup kontinu, urut kronologis, karena datatable generik
     * di hub tidak meregrup ulang di sisi klien), dengan baris "Saldo Awal"
     * sintetis di depan grup (hanya jika belum ada baris riwayat migrasi
     * `saldo_awal` — lihat OpeningBalanceLockService::materializeStock())
     * dan baris "Saldo Akhir" di penutup setiap grup — meniru
     * jurnal-buku-besar.blade.php (Tanggal/Keterangan/Debit/Kredit/Saldo).
     */
    private function persediaanKartu(): Collection
    {
        $groups = StockLedgerEntry::query()
            ->with(['product', 'branch'])
            ->orderBy('product_id')
            ->orderBy('branch_id')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (StockLedgerEntry $entry) => $entry->product_id.'-'.$entry->branch_id);

        $rows = collect();

        foreach ($groups as $group) {
            $first = $group->first();
            $barang = $first->product->name ?? '-';
            $cabang = $first->branch->name ?? '-';

            if ($first->transaction_type !== 'saldo_awal') {
                $rows->push([
                    'tanggal' => $first->transaction_date->format('d-m-Y'),
                    'barang' => $barang,
                    'cabang' => $cabang,
                    'jenis' => 'Saldo Awal',
                    'debet' => '-',
                    'kredit' => '-',
                    'harga' => '-',
                    'saldo' => '0.0000',
                ]);
            }

            foreach ($group as $entry) {
                $rows->push([
                    'tanggal' => $entry->transaction_date->format('d-m-Y'),
                    'barang' => $barang,
                    'cabang' => $cabang,
                    'jenis' => $entry->transaction_type === 'saldo_awal' ? 'Saldo Awal' : ucfirst(str_replace('_', ' ', $entry->transaction_type)),
                    'debet' => (string) $entry->qty_in,
                    'kredit' => (string) $entry->qty_out,
                    'harga' => $this->rupiah((float) $entry->unit_cost),
                    'saldo' => (string) $entry->running_qty,
                ]);
            }

            $last = $group->last();
            $rows->push([
                'tanggal' => $last->transaction_date->format('d-m-Y'),
                'barang' => $barang,
                'cabang' => $cabang,
                'jenis' => 'Saldo Akhir',
                'debet' => '-',
                'kredit' => '-',
                'harga' => '-',
                'saldo' => (string) $last->running_qty,
            ]);
        }

        return $rows;
    }

    private function saldoPersediaan(): Collection
    {
        $allowed = auth()->user()->allowedBranchIds();
        $branchId = $allowed === null ? null : ($allowed[0] ?? null);

        return $this->inventoryReportService->saldoPersediaan($branchId)
            ->map(fn (array $row) => [
                'kode' => $row['product']->code ?? '-',
                'barang' => $row['product']->name ?? '-',
                'qty' => $row['qty'],
                'nilai' => $this->rupiah((float) $row['value']),
            ]);
    }

    private function kategoriAktivaTetap(): Collection
    {
        return FixedAssetCategory::query()->orderBy('name')->get()->map(fn (FixedAssetCategory $category) => [
            'code' => $category->code,
            'name' => $category->name,
            'default_depreciation_method' => ucfirst($category->default_depreciation_method),
            'default_useful_life_months' => (string) $category->default_useful_life_months,
            'status' => $category->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function aktivaTetap(): Collection
    {
        return FixedAsset::query()
            ->with(['category', 'branch'])
            ->orderBy('code')
            ->get()
            ->map(fn (FixedAsset $asset) => [
                'code' => $asset->code,
                'name' => $asset->name,
                'kategori' => $asset->category->name ?? '-',
                'cabang' => $asset->branch->name ?? '-',
                'acquisition_date' => $asset->acquisition_date->format('d-m-Y'),
                'acquisition_cost' => $this->rupiah((float) $asset->acquisition_cost),
                'akumulasi_penyusutan' => $this->rupiah((float) $asset->accumulatedDepreciation()),
                'book_value' => $this->rupiah((float) $asset->bookValue()),
                'status' => ucfirst($asset->status),
            ]);
    }

    private function pelepasanAktivaTetap(): Collection
    {
        return FixedAssetDisposal::query()
            ->with('fixedAsset')
            ->orderByDesc('disposal_date')
            ->get()
            ->map(fn (FixedAssetDisposal $disposal) => [
                'tanggal' => $disposal->disposal_date->format('d-m-Y'),
                'kode' => $disposal->fixedAsset->code ?? '-',
                'nama' => $disposal->fixedAsset->name ?? '-',
                'jenis_pelepasan' => ucfirst($disposal->disposal_type),
                'nilai_jual' => $this->rupiah((float) $disposal->sale_amount),
                'nilai_buku' => $this->rupiah((float) $disposal->book_value_at_disposal),
                'untung_rugi' => $this->rupiah((float) $disposal->gain_loss_amount),
                'status' => $disposal->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function jenisRetribusi(): Collection
    {
        return RetributionType::query()->orderBy('sort_order')->get()->map(fn (RetributionType $type) => [
            'code' => $type->code,
            'name' => $type->name,
            'percentage' => number_format((float) $type->percentage, 2).'%',
            'status' => $type->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function retribusiUpf(): Collection
    {
        return RetributionTransaction::query()
            ->with('branch')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RetributionTransaction $trx) => [
                'tanggal' => $trx->transaction_date->format('d-m-Y'),
                'transaction_number' => $trx->transaction_number,
                'payer_name' => $trx->payer_name,
                'payer_type' => ucfirst($trx->payer_type),
                'cabang' => $trx->branch->name ?? '-',
                'total_amount' => $this->rupiah((float) $trx->total_amount),
                'payment_method' => ucfirst($trx->payment_method),
                'status' => $trx->isCancelled() ? 'Dibatalkan' : 'Normal',
            ]);
    }

    private function unitUsaha(): Collection
    {
        return BusinessUnit::query()->orderBy('name')->get()->map(fn (BusinessUnit $unit) => [
            'code' => $unit->code,
            'name' => $unit->name,
            'status' => $unit->is_active ? 'Aktif' : 'Nonaktif',
        ]);
    }

    private function transaksiUnitUsaha(): Collection
    {
        return BusinessUnitTransaction::query()
            ->with(['businessUnit', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (BusinessUnitTransaction $trx) => [
                'tanggal' => $trx->created_at->format('d-m-Y'),
                'unit_usaha' => $trx->businessUnit->name ?? '-',
                'cabang' => $trx->branch->name ?? '-',
                'jenis' => ucfirst($trx->type),
                'amount' => $this->rupiah((float) $trx->amount),
                'description' => $trx->description ?: '-',
            ]);
    }

    private function transaksiPos(): Collection
    {
        return PosSale::query()
            ->with('branch')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PosSale $sale) => [
                'tanggal' => $sale->sale_date->format('d-m-Y'),
                'sale_number' => $sale->sale_number,
                'cabang' => $sale->branch->name ?? '-',
                'metode_bayar' => ucfirst(str_replace('_', ' ', $sale->payment_method)),
                'total' => $this->rupiah((float) $sale->total_amount),
            ]);
    }

    private function transaksiTeller(): Collection
    {
        return TellerCashTransaction::query()
            ->with(['category', 'branch'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (TellerCashTransaction $trx) => [
                'tanggal' => $trx->created_at->format('d-m-Y H:i'),
                'kategori' => $trx->category->name ?? '-',
                'akun_kas' => $trx->cashAccount->name ?? '-',
                'cabang' => $trx->branch->name ?? '-',
                'jumlah' => $this->rupiah((float) $trx->amount),
                'keterangan' => $trx->description ?: '-',
            ]);
    }

    private function kalenderKegiatan(): Collection
    {
        $allowed = auth()->user()->allowedBranchIds();
        $statusLabels = ['terjadwal' => 'Terjadwal', 'dibatalkan' => 'Dibatalkan', 'selesai' => 'Selesai'];

        return CooperativeEvent::query()
            ->orderByDesc('start_at')
            ->get()
            ->filter(function (CooperativeEvent $event) use ($allowed) {
                if ($allowed === null) {
                    return true;
                }

                $ids = $event->branchIds();

                return $ids === null || array_intersect($ids, $allowed) !== [];
            })
            ->map(fn (CooperativeEvent $event) => [
                'tanggal' => $event->start_at->format('d-m-Y H:i'),
                'title' => $event->title,
                'location' => $event->location ?: '-',
                'status' => $statusLabels[$event->status] ?? ucfirst($event->status),
            ])
            ->values();
    }

    private function jurnalUmum(): Collection
    {
        return JournalEntry::query()
            ->with(['branch', 'createdBy', 'lines'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'tanggal' => $entry->entry_date->format('d-m-Y'),
                'description' => $entry->description,
                'cabang' => $entry->branch->name ?? '-',
                'source_type' => $entry->source_type ? class_basename($entry->source_type) : 'Manual',
                'total_debit' => $this->rupiah((float) $entry->lines->sum('debit')),
                'total_kredit' => $this->rupiah((float) $entry->lines->sum('credit')),
                'created_by_name' => $entry->createdBy->name ?? '-',
            ]);
    }

    private function pengguna(): Collection
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->map(
                    fn (string $role) => ucfirst(str_replace('_', ' ', $role))
                )->implode(', ') ?: '-',
                'status' => $user->is_active ? 'Aktif' : 'Nonaktif',
            ]);
    }
}
