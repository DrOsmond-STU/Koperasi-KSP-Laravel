<?php

namespace App\Http\Controllers\Staf;

use App\Exceptions\Retribution\RetributionException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelRetributionTransactionRequest;
use App\Http\Requests\GenerateRetributionReportRequest;
use App\Http\Requests\StoreRetributionTransactionRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\RetributionTransaction;
use App\Models\RetributionType;
use App\Services\Dashboard\RetributionDashboardService;
use App\Services\Reporting\ReportTypeRegistry;
use App\Services\Retribution\RetributionReportService;
use App\Services\Retribution\RetributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RetributionController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(
        private readonly RetributionService $retributionService,
        private readonly RetributionDashboardService $dashboardService,
        private readonly RetributionReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('retribusi_upf.read');

        return view('staf.retribusi-upf', [
            ...$this->baseViewData($request),
            'tab' => $request->string('tab')->value() ?: 'dashboard',
            'reportRows' => null,
            'reportColumns' => [],
        ]);
    }

    public function store(StoreRetributionTransactionRequest $request): RedirectResponse
    {
        $payerType = $request->validated('payer_type');

        $member = $payerType === 'anggota'
            ? Member::query()->findOrFail($request->validated('member_id'))
            : null;

        $retributionType = $payerType === 'umum' && $request->filled('retribution_type_id')
            ? RetributionType::query()->findOrFail($request->validated('retribution_type_id'))
            : null;

        try {
            $transaction = $this->retributionService->record(
                branchId: (int) $request->validated('branch_id'),
                payerType: $payerType,
                payerName: $request->validated('payer_name'),
                member: $member,
                totalAmount: (float) $request->validated('total_amount'),
                paymentMethod: $request->validated('payment_method'),
                createdBy: $request->user()->id,
                description: $request->validated('description'),
                date: $request->filled('transaction_date') ? Carbon::parse($request->validated('transaction_date')) : null,
                retributionType: $retributionType,
            );
        } catch (RetributionException $exception) {
            return redirect()->route('staf.retribusi-upf.index', ['tab' => 'transaksi'])->with('error', $exception->getMessage());
        }

        return redirect()->route('staf.retribusi-upf.index', ['tab' => 'transaksi'])
            ->with('status', "Retribusi {$transaction->transaction_number} sebesar Rp ".number_format((float) $transaction->total_amount, 0, ',', '.').' berhasil dicatat.');
    }

    public function cancel(CancelRetributionTransactionRequest $request, RetributionTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->canBeCancelledBy($request->user()), 403, 'Anda hanya bisa membatalkan transaksi yang Anda buat sendiri.');

        $this->retributionService->reverseTransaction($transaction, $request->validated('reason'), $request->user()->id);

        return redirect()->route('staf.retribusi-upf.index', ['tab' => 'transaksi'])
            ->with('status', "Transaksi {$transaction->transaction_number} berhasil dibatalkan.");
    }

    public function generateLaporan(GenerateRetributionReportRequest $request): View
    {
        $columns = $request->validated('columns');

        $rows = $this->reportService->retribusiUpf([
            'branch_id' => $request->validated('branch_id'),
            'period_start' => $request->validated('period_start'),
            'period_end' => $request->validated('period_end'),
        ])->map(fn ($row) => collect($row)->only($columns)->all());

        $data = $this->baseViewData($request);

        $reportColumns = collect(ReportTypeRegistry::columnsFor('retribusi_upf'))
            ->merge($data['activeTypes']->mapWithKeys(fn ($type) => ["retribusi_line_{$type->id}" => $type->name]))
            ->only($columns);

        return view('staf.retribusi-upf', [
            ...$data,
            'tab' => 'laporan',
            'reportRows' => $rows,
            'reportColumns' => $reportColumns,
        ]);
    }

    /**
     * Laporan Harian Retribusi UPF — satu tanggal, ttd Petugas UPF + Teller
     * + Pengurus. Reuse langsung RetributionReportService::retribusiUpf()
     * dengan period_start === period_end, tidak ada query baru.
     */
    public function printHarian(Request $request): Response
    {
        $this->authorize('retribusi_upf.read');

        $branchId = $this->resolveBranchId($request);
        $date = $request->string('tanggal')->value() ?: now()->toDateString();

        $rows = $this->reportService->retribusiUpf([
            'branch_id' => $branchId,
            'period_start' => $date,
            'period_end' => $date,
        ]);

        $pdf = $this->renderPrintPdf('prints.laporan.upf-harian', [
            'date' => $date,
            'branch' => $branchId ? Branch::query()->find($branchId) : null,
            'rows' => $rows,
            'activeTypes' => RetributionType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);

        return $pdf->download('laporan-upf-'.$date.'.pdf');
    }

    /**
     * Cetak "Rekap Pendapatan UPF" (potrait, periode fleksibel harian s/d
     * bulanan). Menampilkan breakdown per jenis retribusi + total kas
     * masuk. Reuse RetributionReportService::rekapPendapatan().
     */
    public function printRekap(Request $request): Response
    {
        $this->authorize('retribusi_upf.read');

        $branchId = $this->resolveBranchId($request);
        $periodStart = $request->date('period_start')?->toDateString() ?: now()->startOfMonth()->toDateString();
        $periodEnd = $request->date('period_end')?->toDateString() ?: now()->toDateString();

        // Guard: kalau end < start, swap otomatis supaya cetakan tetap
        // masuk akal dan tidak menampilkan hasil kosong yang membingungkan.
        if ($periodEnd < $periodStart) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        $rekap = $this->reportService->rekapPendapatan([
            'branch_id' => $branchId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $pdf = $this->renderPrintPdf('prints.laporan.upf-rekap', [
            'rekap' => $rekap,
            'branch' => $branchId ? Branch::query()->find($branchId) : null,
        ], orientationOverride: 'portrait');

        return $pdf->download('rekap-upf-'.$periodStart.'_sd_'.$periodEnd.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function baseViewData(Request $request): array
    {
        $branchId = $this->resolveBranchId($request);
        $activeTypes = RetributionType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Jenis retribusi "split" (persentase < 100, dijumlahkan harus 100)
        // dipakai untuk pembagian otomatis transaksi Anggota. Jenis "umum"
        // (persentase = 100) dipakai per-transaksi Umum (dipilih satu).
        $splitTypes = $activeTypes->filter(fn (RetributionType $type) => (float) $type->percentage < 100)->values();
        $umumTypes = $activeTypes->filter(fn (RetributionType $type) => (float) $type->percentage >= 100)->values();

        // Anggota untuk retribusi (Kios/Blok). Kalau master data KIOS/BLOK
        // sudah terisi di produksi, kita batasi ke sana; kalau kosong (mis.
        // production baru, master data belum di-set), fallback ke semua
        // anggota yang belum "keluar" — supaya daftar tidak pernah kosong
        // dan petugas tetap bisa memilih pembayar.
        $membersQuery = Member::query()
            ->whereIn('status', ['aktif', 'calon', 'nonaktif'])
            ->orderBy('name');

        $hasKiosBlokMembers = (clone $membersQuery)
            ->whereHas('memberType', fn ($q) => $q->whereIn('code', ['KIOS', 'BLOK']))
            ->exists();

        if ($hasKiosBlokMembers) {
            $membersQuery->whereHas('memberType', fn ($q) => $q->whereIn('code', ['KIOS', 'BLOK']));
        }

        return [
            'summary' => $this->dashboardService->summary($branchId),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'activeTypes' => $activeTypes,
            'splitTypes' => $splitTypes,
            'umumTypes' => $umumTypes,
            'splitPercentageTotal' => $splitTypes->sum('percentage'),
            'activePercentageTotal' => $activeTypes->sum('percentage'),
            'members' => $membersQuery->with('memberType')->get(),
            'recentTransactions' => RetributionTransaction::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->with(['lines', 'branch'])
                ->latest('transaction_date')
                ->latest('id')
                ->limit(20)
                ->get(),
            'staticColumns' => ReportTypeRegistry::columnsFor('retribusi_upf'),
        ];
    }

    /**
     * Sama pola dengan DashboardController::resolveBranchId(), kecuali
     * default-nya: modul Retribusi ini secara konsep terikat ke cabang
     * "Unit Pengelola Fasilitas (UPF)" — begitu user belum memilih cabang
     * lain secara eksplisit, tampilkan data cabang itu (bukan konsolidasi
     * semua cabang), supaya KPI dashboard & pilihan Cabang di form
     * Transaksi konsisten menunjuk cabang yang sama.
     */
    private function resolveBranchId(Request $request): ?int
    {
        $allowed = $request->user()->allowedBranchIds();
        $requested = $request->integer('branch_id') ?: null;

        if ($allowed === null) {
            return $requested ?? $this->defaultUpfBranchId();
        }

        if ($requested !== null && ! in_array($requested, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke cabang ini.');
        }

        return $requested ?? $this->preferredAllowedBranchId($allowed);
    }

    /**
     * Cabang "Unit Pengelola Fasilitas (UPF)" dicocokkan lewat nama
     * (bukan id/code tetap) karena cabang ini didaftarkan langsung oleh
     * pengurus lewat menu Master Cabang, bukan lewat seeder — tidak ada
     * id/code yang bisa diasumsikan konsisten di semua instalasi.
     */
    private function defaultUpfBranchId(): ?int
    {
        return Branch::query()->where('name', 'LIKE', '%UPF%')->value('id');
    }

    /**
     * @param  array<int, int>  $allowed
     */
    private function preferredAllowedBranchId(array $allowed): ?int
    {
        $upfId = $this->defaultUpfBranchId();

        if ($upfId !== null && in_array($upfId, $allowed, true)) {
            return $upfId;
        }

        return $allowed[0] ?? null;
    }

    private function availableBranches(Request $request)
    {
        $allowed = $request->user()->allowedBranchIds();

        return $allowed === null
            ? Branch::query()->where('is_active', true)->get()
            : Branch::query()->where('is_active', true)->whereIn('id', $allowed)->get();
    }
}
