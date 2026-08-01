<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Accounting\JournalPostingException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeneralJournalRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\Accounting\JournalEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jurnal Umum — posting manual debit/kredit untuk transaksi DI LUAR
 * aktivitas simpan-pinjam koperasi (mis. beban kantor, pendapatan lain,
 * koreksi saldo). Berbeda dari Jurnal Penyesuaian (JournalAdjustmentController,
 * yang HANYA membalik entri yang sudah ada) — ini membuat entri baru dari
 * nol. Satu-satunya penulis ke journal_entries/journal_lines tetap
 * JournalEngine::post() (LED-01..08) — controller ini hanya merakit baris
 * dari input form.
 */
class GeneralJournalController extends Controller
{
    use GeneratesPrintPdf;

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function create(Request $request): View
    {
        $this->authorize('jurnal.create');

        $branchId = $this->resolveBranchId($request);

        return view('admin.jurnal-umum', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'recentEntries' => JournalEntry::query()
                ->whereNull('source_type')
                ->whereNull('reversal_of_entry_id')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->with(['lines.account', 'branch'])
                ->latest()
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(StoreGeneralJournalRequest $request): RedirectResponse
    {
        $lines = collect($request->validated('lines'))
            ->map(fn (array $line) => [
                'chart_of_account_id' => $line['chart_of_account_id'],
                'debit' => $line['position'] === 'debit' ? $line['amount'] : 0,
                'credit' => $line['position'] === 'kredit' ? $line['amount'] : 0,
            ])
            ->all();

        try {
            $entry = $this->journalEngine->post([
                'branch_id' => $request->validated('branch_id'),
                'entry_date' => $request->validated('entry_date'),
                'description' => $request->validated('description'),
                'created_by' => $request->user()->id,
                'lines' => $lines,
            ]);
        } catch (JournalPostingException $exception) {
            return redirect()->route('admin.jurnal-umum.create')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.jurnal-umum.create')
            ->with('status', "Jurnal umum #{$entry->id} berhasil diposting.");
    }

    public function print(JournalEntry $entry): Response
    {
        $this->authorize('jurnal.read');

        $pdf = $this->renderPrintPdf('prints.jurnal.entry', [
            'entry' => $entry->load('lines.account', 'createdBy', 'branch'),
        ]);

        return $pdf->download('jurnal-umum-'.$entry->id.'.pdf');
    }

    /**
     * Sama seperti DashboardController::resolveBranchId() — menegakkan
     * Cabang Scope (PRD §6): All Branch boleh pilih cabang mana pun,
     * scope terbatas dikunci ke cabang yang diizinkan.
     */
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
