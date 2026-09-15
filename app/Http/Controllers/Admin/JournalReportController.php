<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan Jurnal Transaksi — layar "apa debet dan kredit transaksi ini".
 *
 * Alasan keberadaannya ditulis lengkap di JournalReportService: Buku Besar
 * berpusat pada akun (tidak pernah menampilkan satu transaksi utuh) dan
 * daftar Jurnal Umum hanya memuat posting manual, sehingga jurnal yang
 * lahir dari pencairan pinjaman, angsuran, simpanan, dan retribusi tidak
 * terlihat di mana pun.
 *
 * Murni baca — tidak ada satu pun jalur tulis ke jurnal dari sini.
 */
class JournalReportController extends Controller
{
    use GeneratesPrintPdf;

    /** Batas baris cetakan; disebutkan di view supaya pengguna tahu kalau kena. */
    private const BATAS_CETAK = 1000;

    public function __construct(private readonly JournalReportService $reportService) {}

    public function index(Request $request): View
    {
        $this->authorize('jurnal.read');

        $filter = $this->filter($request);

        return view('admin.jurnal-transaksi', [
            'entries' => $this->reportService->paginate($filter),
            'totals' => $this->reportService->totals($filter),
            'reportService' => $this->reportService,
            'filter' => $filter,
            'accounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'branches' => $this->availableBranches($request),
            'sumberOptions' => $this->reportService->sumberTersedia(),
        ]);
    }

    public function print(Request $request): Response
    {
        $this->authorize('jurnal.read');

        $filter = $this->filter($request);
        $entries = $this->reportService->all($filter, self::BATAS_CETAK);

        $pdf = $this->renderPrintPdf('prints.jurnal.transaksi', [
            'entries' => $entries,
            'totals' => $this->reportService->totals($filter),
            'reportService' => $this->reportService,
            'filter' => $filter,
            'branchName' => $filter['branch_id'] === null
                ? 'Semua Cabang'
                : (string) Branch::query()->whereKey($filter['branch_id'])->value('name'),
            'accountName' => $filter['chart_of_account_id'] === null
                ? null
                : ChartOfAccount::query()->whereKey($filter['chart_of_account_id'])->get(['code', 'name'])->map(fn ($a) => $a->code.' — '.$a->name)->first(),
            'dipotong' => $entries->count() >= self::BATAS_CETAK,
            'batasCetak' => self::BATAS_CETAK,
        ]);

        return $pdf->download('jurnal-transaksi-'.$filter['date_from'].'-sd-'.$filter['date_to'].'.pdf');
    }

    /**
     * Rentang bawaan sengaja bulan berjalan, bukan seluruh buku jurnal:
     * membuka layar ini tanpa filter tidak boleh menarik puluhan ribu baris.
     *
     * @return array{date_from: string, date_to: string, branch_id: ?int, chart_of_account_id: ?int, source_type: ?string, q: ?string}
     */
    private function filter(Request $request): array
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'chart_of_account_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'date_from' => $request->input('date_from') ?: now()->startOfMonth()->toDateString(),
            'date_to' => $request->input('date_to') ?: now()->toDateString(),
            'branch_id' => $this->resolveBranchId($request),
            'chart_of_account_id' => $request->integer('chart_of_account_id') ?: null,
            'source_type' => $request->input('source_type') ?: null,
            'q' => $request->input('q') ?: null,
        ];
    }

    /**
     * Sama seperti GeneralLedgerController::resolveBranchId() — menegakkan
     * Cabang Scope (PRD §6). Beda satu hal yang disengaja: cabang TIDAK
     * dipaksa terisi untuk pengguna ber-scope terbatas. BranchScope pada
     * JournalEntry sudah mempersempit hasilnya sendiri, jadi membiarkan
     * kosong berarti "semua cabang yang boleh saya lihat" — bukan kebocoran.
     */
    private function resolveBranchId(Request $request): ?int
    {
        $allowed = $request->user()->allowedBranchIds();
        $requested = $request->integer('branch_id') ?: null;

        if ($requested !== null && $allowed !== null && ! in_array($requested, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $requested;
    }

    private function availableBranches(Request $request)
    {
        $allowed = $request->user()->allowedBranchIds();

        return $allowed === null
            ? Branch::query()->where('is_active', true)->orderBy('name')->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->orderBy('name')->get();
    }
}
