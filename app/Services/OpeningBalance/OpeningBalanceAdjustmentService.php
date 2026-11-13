<?php

namespace App\Services\OpeningBalance;

use App\Exceptions\OpeningBalance\OpeningBalanceAdjustmentException;
use App\Models\ChartOfAccount;
use App\Models\LoanProduct;
use App\Models\OpeningBalanceAdjustment;
use App\Models\OpeningBalanceBatch;
use App\Models\Product;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Ubah / Koreksi Saldo Awal Neraca.
 *
 * Pengguna memasukkan SALDO BARU per akun Neraca (dalam arah saldo normal
 * akun); service menghitung selisih terhadap saldo awal yang berlaku saat
 * ini lalu:
 *
 *  - Batch DRAFT  → baris `opening_balance_coa` akun yang berubah langsung
 *    diganti (belum ada jurnal, jadi aman ditimpa).
 *  - Batch LOCKED → baris `opening_balance_coa` TIDAK disentuh (RUNBOOK
 *    §5.6). Selisihnya diposting sebagai satu jurnal koreksi lewat
 *    JournalEngine (bertanggal cut-off secara default) dan dicatat di
 *    `opening_balance_adjustments` + `_lines` (saldo lama → saldo baru),
 *    dengan konfirmasi ulang kata sandi seperti Jurnal Penyesuaian.
 *
 * Bila total perubahan debit ≠ kredit, selisihnya ditampung ke "akun
 * penyeimbang" pilihan pengguna (lazimnya 3160 SHU Ditahan / Tahun Lalu)
 * supaya jurnal tetap seimbang (LED-02).
 */
class OpeningBalanceAdjustmentService
{
    /**
     * Saldo akun ini dihitung LIVE oleh FinancialReportEngine (bukan dari
     * jurnal), jadi koreksi lewat jurnal tidak akan terlihat di Neraca.
     */
    public const EXCLUDED_ACCOUNT_CODES = ['3150'];

    public const DEFAULT_BALANCING_ACCOUNT_CODE = '3160';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    /**
     * Akun Neraca yang boleh diubah saldo awalnya.
     *
     * @return Collection<int, ChartOfAccount>
     */
    public function adjustableAccounts(): Collection
    {
        return ChartOfAccount::query()
            ->where('statement', 'NERACA')
            ->where('is_postable', true)
            ->whereNotIn('code', self::EXCLUDED_ACCOUNT_CODES)
            ->orderBy('code')
            ->get();
    }

    /**
     * Saldo awal efektif per akun dalam satuan sen, arah debit-positif
     * (debit − kredit): baris COA batch + seluruh koreksi sebelumnya.
     *
     * @return array<int, int>
     */
    public function rawBalancesInCents(OpeningBalanceBatch $batch): array
    {
        $balances = [];

        foreach ($batch->coaLines()->get(['chart_of_account_id', 'position', 'amount']) as $line) {
            $cents = $this->toCents((string) $line->amount);
            $balances[$line->chart_of_account_id] = ($balances[$line->chart_of_account_id] ?? 0)
                + ($line->position === 'debit' ? $cents : -$cents);
        }

        $adjustmentLines = DB::table('opening_balance_adjustment_lines as l')
            ->join('opening_balance_adjustments as a', 'a.id', '=', 'l.opening_balance_adjustment_id')
            ->where('a.opening_balance_batch_id', $batch->id)
            ->get(['l.chart_of_account_id', 'l.debit', 'l.credit']);

        foreach ($adjustmentLines as $line) {
            $balances[$line->chart_of_account_id] = ($balances[$line->chart_of_account_id] ?? 0)
                + $this->toCents((string) $line->debit) - $this->toCents((string) $line->credit);
        }

        return $balances;
    }

    /**
     * Baris tabel untuk form: akun, saldo awal saat ini (arah saldo normal),
     * dan apakah akun ini akun kontrol sub-ledger (Simpanan/Pinjaman/
     * Persediaan) — mengubahnya membuat buku besar tidak lagi sama dengan
     * rincian per anggota/barang.
     *
     * @return Collection<int, array{account: ChartOfAccount, balance: string, is_control: bool}>
     */
    public function accountRows(OpeningBalanceBatch $batch): Collection
    {
        $raw = $this->rawBalancesInCents($batch);
        $controlIds = $this->subLedgerControlAccountIds();

        return $this->adjustableAccounts()->map(fn (ChartOfAccount $account) => [
            'account' => $account,
            'balance' => $this->fromCents($this->toNormalSign($account, $raw[$account->id] ?? 0)),
            'is_control' => in_array($account->id, $controlIds, true),
        ]);
    }

    /**
     * Total sisi Debit vs Kredit saldo awal efektif (untuk banner).
     *
     * @return array{debit: string, credit: string}
     */
    public function totals(OpeningBalanceBatch $batch): array
    {
        $debit = 0;
        $credit = 0;

        foreach ($this->rawBalancesInCents($batch) as $cents) {
            $cents > 0 ? $debit += $cents : $credit -= $cents;
        }

        return ['debit' => $this->fromCents($debit), 'credit' => $this->fromCents($credit)];
    }

    /**
     * @param  array<int|string, string|float|int|null>  $newBalances  [chart_of_account_id => saldo baru, arah saldo normal]
     */
    public function apply(
        OpeningBalanceBatch $batch,
        User $user,
        array $newBalances,
        ?int $balancingAccountId,
        string $reason,
        ?string $entryDate = null,
        ?string $password = null,
    ): ?OpeningBalanceAdjustment {
        if (! $batch->isDraft() && ! Hash::check((string) $password, $user->password)) {
            throw OpeningBalanceAdjustmentException::reAuthFailed();
        }

        $accounts = $this->adjustableAccounts()->keyBy('id');
        $current = $this->rawBalancesInCents($batch);

        // [accountId => ['before' => raw cents, 'after' => raw cents]]
        $changes = [];

        foreach ($newBalances as $accountId => $value) {
            $accountId = (int) $accountId;

            if ($value === null || $value === '' || ! $accounts->has($accountId)) {
                continue;
            }

            $before = $current[$accountId] ?? 0;
            $after = $this->toRaw($accounts[$accountId], $this->toCents((string) $value));

            if ($after !== $before) {
                $changes[$accountId] = ['before' => $before, 'after' => $after];
            }
        }

        if ($changes === []) {
            throw OpeningBalanceAdjustmentException::noChanges();
        }

        // Selisih bersih (debit-positif) yang harus ditampung akun penyeimbang.
        $net = array_sum(array_map(fn ($c) => $c['after'] - $c['before'], $changes));

        if ($net !== 0 && $balancingAccountId !== null && $accounts->has($balancingAccountId)) {
            if (isset($changes[$balancingAccountId])) {
                throw OpeningBalanceAdjustmentException::balancingAccountAlsoChanged();
            }

            $before = $current[$balancingAccountId] ?? 0;
            $changes[$balancingAccountId] = ['before' => $before, 'after' => $before - $net, 'balancing' => true];
            $net = 0;
        }

        if ($batch->isDraft()) {
            $this->rewriteDraftLines($batch, $changes);

            return null;
        }

        if ($net !== 0) {
            throw OpeningBalanceAdjustmentException::balancingAccountRequired(number_format(abs($net) / 100, 2, ',', '.'));
        }

        $entryDate = $entryDate ? Carbon::parse($entryDate) : $batch->cutoff_date->copy();

        if ($entryDate->lt($batch->cutoff_date)) {
            throw OpeningBalanceAdjustmentException::entryDateBeforeCutoff($batch->cutoff_date->translatedFormat('d M Y'));
        }

        return DB::transaction(function () use ($batch, $user, $changes, $accounts, $reason, $entryDate) {
            $lines = [];
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($changes as $accountId => $change) {
                $delta = $change['after'] - $change['before'];
                $debit = max($delta, 0);
                $credit = max(-$delta, 0);
                $totalDebit += $debit;
                $totalCredit += $credit;

                $lines[] = [
                    'chart_of_account_id' => $accountId,
                    'balance_before' => $this->fromCents($this->toNormalSign($accounts[$accountId], $change['before'])),
                    'balance_after' => $this->fromCents($this->toNormalSign($accounts[$accountId], $change['after'])),
                    'debit' => $this->fromCents($debit),
                    'credit' => $this->fromCents($credit),
                    'is_balancing' => $change['balancing'] ?? false,
                ];
            }

            $adjustment = OpeningBalanceAdjustment::query()->create([
                'opening_balance_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'entry_date' => $entryDate->toDateString(),
                'reason' => $reason,
                'total_debit' => $this->fromCents($totalDebit),
                'total_credit' => $this->fromCents($totalCredit),
                'created_by' => $user->id,
            ]);

            $adjustment->lines()->createMany($lines);

            $journal = $this->journalEngine->post([
                'branch_id' => $batch->branch_id,
                'entry_date' => $entryDate->toDateString(),
                'description' => "Koreksi saldo awal neraca (batch #{$batch->id}): {$reason}",
                'created_by' => $user->id,
                'source' => $adjustment,
                'lines' => array_map(fn ($line) => [
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ], $lines),
            ]);

            $adjustment->update(['journal_entry_id' => $journal->id]);

            return $adjustment->load('lines.account');
        });
    }

    /**
     * @param  array<int, array{before: int, after: int}>  $changes
     */
    private function rewriteDraftLines(OpeningBalanceBatch $batch, array $changes): void
    {
        DB::transaction(function () use ($batch, $changes) {
            foreach ($changes as $accountId => $change) {
                $batch->coaLines()->where('chart_of_account_id', $accountId)->delete();

                if ($change['after'] !== 0) {
                    $batch->coaLines()->create([
                        'chart_of_account_id' => $accountId,
                        'position' => $change['after'] > 0 ? 'debit' : 'kredit',
                        'amount' => $this->fromCents(abs($change['after'])),
                    ]);
                }
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function subLedgerControlAccountIds(): array
    {
        return collect()
            ->merge(SavingsProduct::query()->pluck('coa_liability_account_id'))
            ->merge(LoanProduct::query()->pluck('coa_receivable_account_id'))
            ->merge(Product::query()->pluck('coa_inventory_account_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function toNormalSign(ChartOfAccount $account, int $rawCents): int
    {
        return $account->normal_balance === 'DEBIT' ? $rawCents : -$rawCents;
    }

    private function toRaw(ChartOfAccount $account, int $normalCents): int
    {
        return $account->normal_balance === 'DEBIT' ? $normalCents : -$normalCents;
    }

    private function toCents(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }

    private function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }
}
