<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Accounting\AdjustmentException;
use App\Exceptions\Accounting\JournalPostingException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeneralJournalRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AdjustmentService;
use App\Services\Accounting\JournalEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    public function __construct(
        private readonly JournalEngine $journalEngine,
        private readonly AdjustmentService $adjustmentService,
    ) {}

    public function create(Request $request): View
    {
        $this->authorize('jurnal.create');

        $branchId = $this->resolveBranchId($request);

        // Riwayat hanya jurnal yang diinput dari menu ini. Sebelumnya cukup
        // "tanpa source_type", yang ternyata ikut memuat jurnal Retribusi UPF
        // (puluhan per hari) sehingga jurnal umum tertimbun — lihat
        // queryJurnalManual().
        $recentEntries = $this->queryJurnalManual()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['lines.account', 'branch', 'reversals'])
            ->latest()
            ->latest('id')
            ->limit(100)
            ->get();

        $user = $request->user();

        return view('admin.jurnal-umum', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'recentEntries' => $recentEntries,
            'idBisaDibatalkan' => $recentEntries
                ->filter(fn (JournalEntry $entry) => $entry->reversals->isEmpty()
                    && $this->bolehMembatalkan($user, $entry))
                ->pluck('id')
                ->all(),
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

    /**
     * Pembatalan Jurnal Umum (permintaan Okt 2026) — KHUSUS jurnal yang
     * diinput dari menu ini; jurnal milik modul lain (simpanan, pinjaman,
     * Retribusi UPF, Kas Teller, dst.) dibatalkan lewat modul asalnya supaya
     * status transaksinya ikut tercatat.
     *
     * Entri asal tidak pernah diubah/dihapus (LED-06): pembatalan memposting
     * jurnal pembalik lewat AdjustmentService::reverse() — jalur yang sama
     * dengan Jurnal Penyesuaian, lengkap dengan konfirmasi ulang kata sandi,
     * penguncian baris, dan penolakan pembatalan ganda.
     */
    public function cancel(Request $request, JournalEntry $entry): RedirectResponse
    {
        abort_unless($this->bolehMembatalkan($request->user(), $entry), 403, 'Anda tidak berhak membatalkan jurnal ini.');

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi untuk konfirmasi pembatalan.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.jurnal-umum.create')->with('error', $validator->errors()->first());
        }

        if (! $this->queryJurnalManual()->whereKey($entry->id)->exists()) {
            return redirect()->route('admin.jurnal-umum.create')
                ->with('error', "Jurnal #{$entry->id} bukan jurnal yang diinput dari menu Jurnal Umum — batalkan lewat modul asalnya.");
        }

        try {
            $pembalik = $this->adjustmentService->reverse(
                $entry,
                $validator->validated()['reason'],
                $request->user(),
                $validator->validated()['password'],
            );
        } catch (AdjustmentException $exception) {
            return redirect()->route('admin.jurnal-umum.create')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.jurnal-umum.create')
            ->with('status', "Jurnal umum #{$entry->id} dibatalkan — jurnal pembalik #{$pembalik->id} sudah diposting.");
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
     * Jurnal yang diinput dari menu Jurnal Umum: tanpa source_type, bukan
     * jurnal pembalik, dan bukan milik Retribusi UPF / Kas Teller — dua modul
     * itu juga memposting tanpa source_type, tapi menyimpan journal_entry_id
     * di tabel transaksinya sendiri, jadi bisa dikecualikan dengan pasti.
     */
    private function queryJurnalManual(): Builder
    {
        return JournalEntry::query()
            ->whereNull('source_type')
            ->whereNull('reversal_of_entry_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('retribution_transactions')
                ->whereColumn('retribution_transactions.journal_entry_id', 'journal_entries.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('teller_cash_transactions')
                ->whereColumn('teller_cash_transactions.journal_entry_id', 'journal_entries.id'));
    }

    /**
     * Pemegang jurnal.adjust (mis. Bendahara) boleh membatalkan jurnal umum
     * siapa pun; pemegang jurnal.create hanya jurnal yang diinputnya sendiri.
     */
    private function bolehMembatalkan(User $user, JournalEntry $entry): bool
    {
        return $user->can('jurnal.adjust')
            || ($user->can('jurnal.create') && (int) $entry->created_by === (int) $user->id);
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
