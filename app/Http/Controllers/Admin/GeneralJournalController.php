<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Accounting\AdjustmentException;
use App\Exceptions\Accounting\JournalPostingException;
use App\Http\Controllers\Concerns\GeneratesPrintPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGeneralJournalRequest;
use App\Http\Requests\UpdateGeneralJournalRequest;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AdjustmentService;
use App\Services\Accounting\JournalEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    /** Jumlah maksimum baris hasil pencarian riwayat. */
    private const BATAS_HASIL_CARI = 300;

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
        // Pencarian riwayat (nomor jurnal, keterangan, tanggal, nominal,
        // cabang) dijalankan di server atas SELURUH jurnal manual — bukan
        // hanya 100 terbaru — supaya jurnal lama (mis. diinput sebulan lalu)
        // tetap bisa ditemukan, dibatalkan atau dicetak.
        $filter = $this->filterRiwayat($request);
        $adaFilter = collect($filter)->except('cabang')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()
            || $filter['cabang'] !== null;
        $batas = $adaFilter ? self::BATAS_HASIL_CARI : 100;

        $recentEntries = $this->queryJurnalManual()
            ->when(! $adaFilter && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($adaFilter, fn ($q) => $this->terapkanFilterRiwayat($q, $filter, $request))
            ->with(['lines.account', 'branch', 'reversals'])
            ->latest()
            ->latest('id')
            ->limit($batas)
            ->get();

        $user = $request->user();

        return view('admin.jurnal-umum', [
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'branches' => $this->availableBranches($request),
            'selectedBranchId' => $branchId,
            'recentEntries' => $recentEntries,
            'filter' => $filter,
            'adaFilter' => $adaFilter,
            'batasTercapai' => $recentEntries->count() >= $batas,
            'batasHasil' => $batas,
            'idBisaDibatalkan' => $recentEntries
                ->filter(fn (JournalEntry $entry) => $entry->reversals->isEmpty()
                    && $this->bolehMembatalkan($user, $entry))
                ->pluck('id')
                ->all(),
            'idBisaDiedit' => $recentEntries
                ->filter(fn (JournalEntry $entry) => $entry->reversals->isEmpty()
                    && $this->bolehMengedit($user, $entry))
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

    /**
     * Form edit jurnal umum. Tidak mengubah apa pun sampai user menekan
     * "Simpan Koreksi" — halaman ini hanya membaca entri asal dan menampilkan
     * baris-barisnya kembali ke form.
     */
    public function edit(Request $request, JournalEntry $entry): View|RedirectResponse
    {
        $this->authorize('jurnal.adjust');

        if (! $this->queryJurnalManual()->whereKey($entry->id)->exists()) {
            return redirect()->route('admin.jurnal-umum.create')
                ->with('error', "Jurnal #{$entry->id} bukan jurnal yang diinput dari menu Jurnal Umum — koreksi lewat modul asalnya.");
        }

        if ($entry->reversals()->exists()) {
            return redirect()->route('admin.jurnal-umum.create')
                ->with('error', "Jurnal #{$entry->id} sudah dibatalkan — tidak bisa diedit lagi. Buat entri baru melalui form Jurnal Umum.");
        }

        $entry->load('lines.account');

        return view('admin.jurnal-umum-edit', [
            'entry' => $entry,
            'postableAccounts' => ChartOfAccount::query()->where('is_postable', true)->orderBy('code')->get(),
            'branches' => $this->availableBranches($request),
        ]);
    }

    /**
     * Simpan koreksi: balik entri lama, lalu posting entri pengganti — satu
     * transaksi database di JournalEngine::reverseAndRepost(). Entri asal
     * tidak pernah di-UPDATE/DELETE, jadi jejak audit utuh dan Buku Besar /
     * Laba Rugi / Neraca tetap konsisten (asal + pembalik = nol).
     */
    public function update(UpdateGeneralJournalRequest $request, JournalEntry $entry): RedirectResponse
    {
        if (! $this->queryJurnalManual()->whereKey($entry->id)->exists()) {
            return redirect()->route('admin.jurnal-umum.create')
                ->with('error', "Jurnal #{$entry->id} bukan jurnal yang diinput dari menu Jurnal Umum — koreksi lewat modul asalnya.");
        }

        if (! Hash::check($request->validated('password'), $request->user()->password)) {
            return redirect()->route('admin.jurnal-umum.edit', $entry)
                ->with('error', 'Kata sandi salah — koreksi dibatalkan.');
        }

        if ($entry->reversals()->exists()) {
            return redirect()->route('admin.jurnal-umum.create')
                ->with('error', "Jurnal #{$entry->id} sudah dibatalkan — tidak bisa diedit lagi.");
        }

        $lines = collect($request->validated('lines'))
            ->map(fn (array $line) => [
                'chart_of_account_id' => $line['chart_of_account_id'],
                'debit' => $line['position'] === 'debit' ? $line['amount'] : 0,
                'credit' => $line['position'] === 'kredit' ? $line['amount'] : 0,
            ])
            ->all();

        try {
            $pengganti = $this->journalEngine->reverseAndRepost(
                $entry,
                $request->validated('reason'),
                [
                    'branch_id' => $request->validated('branch_id'),
                    'entry_date' => $request->validated('entry_date'),
                    'description' => $request->validated('description'),
                    'created_by' => $request->user()->id,
                    'lines' => $lines,
                ],
            );
        } catch (JournalPostingException $exception) {
            return redirect()->route('admin.jurnal-umum.edit', $entry)
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.jurnal-umum.create')
            ->with('status', "Jurnal umum #{$entry->id} dikoreksi — jurnal pembalik sudah diposting dan jurnal pengganti #{$pengganti->id} dibuat.");
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
     * Edit = balik + posting ulang, jadi hanya pemegang jurnal.adjust
     * (mis. Bendahara) yang boleh — lebih ketat daripada Batalkan, karena
     * hasilnya mengubah angka laporan, bukan sekadar meniadakan entri.
     */
    private function bolehMengedit(User $user, JournalEntry $entry): bool
    {
        return $user->can('jurnal.adjust');
    }

    /**
     * Sama seperti DashboardController::resolveBranchId() — menegakkan
     * Cabang Scope (PRD §6): All Branch boleh pilih cabang mana pun,
     * scope terbatas dikunci ke cabang yang diizinkan.
     */
    /**
     * @return array{cari: string, dari: ?string, sampai: ?string, nominal: ?string, cabang: ?int}
     */
    private function filterRiwayat(Request $request): array
    {
        $tanggal = fn (string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($k)) ? (string) $request->query($k) : null;
        $nominal = preg_replace('/[^\d,]/', '', (string) $request->query('cari_nominal', ''));
        $nominal = $nominal === '' ? null : str_replace(',', '.', $nominal);

        return [
            'cari' => mb_substr(trim((string) $request->query('cari', '')), 0, 100),
            'dari' => $tanggal('cari_dari'),
            'sampai' => $tanggal('cari_sampai'),
            'nominal' => is_numeric($nominal) ? $nominal : null,
            'cabang' => $request->integer('cari_cabang') ?: null,
        ];
    }

    private function terapkanFilterRiwayat(Builder $query, array $filter, Request $request): Builder
    {
        $allowed = $request->user()->allowedBranchIds();
        if ($filter['cabang'] !== null) {
            abort_if($allowed !== null && ! in_array($filter['cabang'], $allowed, true), 403, 'Anda tidak memiliki akses ke cabang ini.');
            $query->where('branch_id', $filter['cabang']);
        } elseif ($allowed !== null) {
            $query->whereIn('branch_id', $allowed);
        }

        if ($filter['cari'] !== '') {
            $teks = $filter['cari'];
            $query->where(function ($q) use ($teks) {
                $q->where('description', 'like', '%'.addcslashes($teks, '%_\\').'%');
                if (preg_match('/^#?(\d+)$/', $teks, $m)) {
                    $q->orWhere('id', (int) $m[1]);
                }
            });
        }
        if ($filter['dari'] !== null) {
            $query->whereDate('entry_date', '>=', $filter['dari']);
        }
        if ($filter['sampai'] !== null) {
            $query->whereDate('entry_date', '<=', $filter['sampai']);
        }
        if ($filter['nominal'] !== null) {
            $nominal = $filter['nominal'];
            $query->where(fn ($q) => $q
                ->whereHas('lines', fn ($l) => $l->where(fn ($w) => $w->where('debit', $nominal)->orWhere('credit', $nominal)))
                ->orWhereIn('id', fn ($sub) => $sub->select('journal_entry_id')->from('journal_lines')
                    ->groupBy('journal_entry_id')->havingRaw('SUM(debit) = ?', [$nominal])));
        }

        return $query;
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
