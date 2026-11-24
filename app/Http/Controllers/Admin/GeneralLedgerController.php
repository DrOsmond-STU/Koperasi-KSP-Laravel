<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GeneralLedgerExport;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class GeneralLedgerController extends Controller
{
    use GeneratesPrintPdf;

    /** Batas baris mutasi di PDF (Excel tanpa batas). */
    private const BATAS_PDF = 3000;

    public function __construct(private readonly GeneralLedgerService $ledgerService) {}

    public function index(Request $request): View
    {
        $this->authorize('jurnal.read');

        return view('admin.jurnal-buku-besar', $this->dataKartu($request) + [
            'accounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'branches' => $this->availableBranches($request),
        ]);
    }

    /** Ekspor kartu buku besar (filter sama dengan layar) ke Excel. */
    public function exportExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('jurnal.read');

        $data = $this->dataKartu($request);
        if ($data['selectedAccount'] === null) {
            return redirect()->route('admin.jurnal-buku-besar.index', $request->query())->with('error', 'Pilih akun dulu sebelum ekspor Excel.');
        }

        return Excel::download(new GeneralLedgerExport($data), $this->namaBerkas($data).'.xlsx');
    }

    /** Ekspor kartu buku besar (filter sama dengan layar) ke PDF. */
    public function exportPdf(Request $request): Response|RedirectResponse
    {
        $this->authorize('jurnal.read');

        $data = $this->dataKartu($request);
        if ($data['selectedAccount'] === null) {
            return redirect()->route('admin.jurnal-buku-besar.index', $request->query())->with('error', 'Pilih akun dulu sebelum ekspor PDF.');
        }

        // Baris mutasi dibatasi supaya dompdf tidak kehabisan memori pada
        // periode panjang; total & saldo akhir tetap dihitung dari SEMUA baris.
        $dipotong = $data['lines']->count() > self::BATAS_PDF + 1;

        return $this->renderPrintPdf('prints.jurnal.buku-besar', $data + [
            'linesCetak' => $dipotong ? $data['lines']->take(self::BATAS_PDF + 1) : $data['lines'],
            'dipotong' => $dipotong,
            'batasPdf' => self::BATAS_PDF,
            'ringkasan' => $this->ringkasan($data['lines']),
            'branchName' => $this->namaCabang($data['selectedBranchId']),
        ])->download($this->namaBerkas($data).'.pdf');
    }

    /**
     * Data kartu buku besar sesuai filter layar — dipakai layar, Excel dan PDF
     * supaya ketiganya selalu menampilkan angka yang sama.
     *
     * @return array{selectedAccount: ?ChartOfAccount, lines: Collection, periodStart: string, periodEnd: string, selectedBranchId: ?int, isConsolidated: bool, showCancelled: bool}
     */
    private function dataKartu(Request $request): array
    {
        $branchId = $this->resolveBranchId($request);
        $accountId = $request->integer('chart_of_account_id') ?: null;
        $periodStart = $request->input('period_start', now()->startOfMonth()->toDateString());
        $periodEnd = $request->input('period_end', now()->toDateString());

        $account = $accountId !== null ? ChartOfAccount::query()->find($accountId) : null;

        // Bawaan: jurnal yang dibatalkan (asal + pembaliknya) disembunyikan
        // supaya kartu buku besar tidak penuh riak bolak-balik yang net-nya
        // nol. Saldo berjalan tidak terpengaruh — lihat catatan di
        // GeneralLedgerService::linesFor().
        $showCancelled = $request->boolean('tampilkan_dibatalkan');

        return [
            'selectedAccount' => $account,
            'lines' => $account !== null ? $this->ledgerService->linesFor($account, $branchId, $periodStart, $periodEnd, $showCancelled) : collect(),
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'selectedBranchId' => $branchId,
            'isConsolidated' => $branchId === null,
            'showCancelled' => $showCancelled,
        ];
    }

    /** @return array{saldo_awal: float, total_debit: float, total_kredit: float, saldo_akhir: float} */
    public static function ringkasan(Collection $lines): array
    {
        $mutasi = $lines->reject(fn ($l) => $l['is_opening'] ?? false);

        return [
            'saldo_awal' => (float) ($lines->first()['running_balance'] ?? 0),
            'total_debit' => round($mutasi->sum(fn ($l) => (float) $l['debit']), 2),
            'total_kredit' => round($mutasi->sum(fn ($l) => (float) $l['credit']), 2),
            'saldo_akhir' => (float) ($lines->last()['running_balance'] ?? 0),
        ];
    }

    private function namaCabang(?int $branchId): string
    {
        return $branchId === null
            ? 'Konsolidasi Seluruh Cabang'
            : (string) Branch::query()->whereKey($branchId)->value('name');
    }

    private function namaBerkas(array $data): string
    {
        return 'buku-besar-'.$data['selectedAccount']->code.'-'.$data['periodStart'].'-sd-'.$data['periodEnd'];
    }

    private function resolveBranchId(Request $request): ?int
    {
        $allowed = $request->user()->allowedBranchIds();
        $requested = $request->integer('branch_id') ?: null;

        if ($allowed === null) {
            return $requested;
        }

        if ($requested !== null && ! in_array($requested, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $requested ?? ($allowed[0] ?? null);
    }

    private function availableBranches(Request $request)
    {
        $allowed = $request->user()->allowedBranchIds();

        return $allowed === null
            ? Branch::query()->where('is_active', true)->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get();
    }
}
