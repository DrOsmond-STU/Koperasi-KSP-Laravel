<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\JournalPostingException;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for `journal_entries` / `journal_lines` (PRD §2.3,
 * ARCHITECTURE §3.2/§4). Every module that produces accounting impact
 * (Simpanan, Pinjaman, UPF, Kas Teller, Unit Usaha Lain, POS, Pembelian,
 * Aktiva Tetap, Saldo Awal) must call JournalEngine::post() — no module
 * writes journal_lines directly.
 *
 * Guarantees:
 *  - atomic (wrapped in DB::transaction, all-or-nothing — LED-03)
 *  - balanced (Σdebit = Σcredit, checked in integer cents to avoid float
 *    rounding errors — LED-02, LED-08)
 *  - idempotent (repeated call with the same idempotency_key returns the
 *    original entry instead of duplicating it — LED-04)
 *  - append-only (corrections go through reverse(), never UPDATE/DELETE —
 *    LED-06)
 *  - respects closed accounting periods (LED-05) and non-postable accounts
 */
class JournalEngine
{
    /**
     * @param  array{
     *     branch_id: int,
     *     entry_date: string|\DateTimeInterface,
     *     description: string,
     *     created_by: int,
     *     source?: Model|null,
     *     idempotency_key?: string|null,
     *     lines: array<int, array{account_code?: string, chart_of_account_id?: int, debit?: float|string, credit?: float|string}>,
     * }  $data
     */
    public function post(array $data): JournalEntry
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }
        }

        $lines = $data['lines'] ?? [];

        if (count($lines) < 2) {
            throw JournalPostingException::emptyLines();
        }

        $resolvedLines = array_map(fn (array $line) => $this->resolveLine($line), $lines);

        $this->assertBalanced($resolvedLines);
        $this->assertPeriodOpen((int) $data['branch_id'], Carbon::parse($data['entry_date']));

        return DB::transaction(function () use ($data, $resolvedLines, $idempotencyKey) {
            $entry = JournalEntry::query()->create([
                'branch_id' => $data['branch_id'],
                'entry_date' => Carbon::parse($data['entry_date'])->toDateString(),
                'description' => $data['description'],
                'created_by' => $data['created_by'],
                'source_type' => isset($data['source']) ? $data['source']::class : null,
                'source_id' => ($data['source'] ?? null)?->getKey(),
            ]);

            foreach ($resolvedLines as $line) {
                $entry->lines()->create([
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            if ($idempotencyKey !== null) {
                $this->claimIdempotencyKey($idempotencyKey, $entry);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Posts a reversing entry that mirrors $entry with debit/credit swapped.
     * The original entry is never modified (append-only — LED-06).
     */
    public function reverse(JournalEntry $entry, string $reason, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason, $userId) {
            $reversal = JournalEntry::query()->create([
                'branch_id' => $entry->branch_id,
                'entry_date' => now()->toDateString(),
                'description' => "Koreksi/pembalik jurnal #{$entry->id}: {$reason}",
                'created_by' => $userId,
                'reversal_of_entry_id' => $entry->id,
            ]);

            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                ]);
            }

            return $reversal->load('lines');
        });
    }

    /**
     * @return array{chart_of_account_id: int, debit: string, credit: string}
     */
    private function resolveLine(array $line): array
    {
        $account = isset($line['chart_of_account_id'])
            ? ChartOfAccount::query()->find($line['chart_of_account_id'])
            : ChartOfAccount::query()->where('code', $line['account_code'] ?? null)->first();

        if ($account === null) {
            throw JournalPostingException::unknownAccount($line['account_code'] ?? (string) ($line['chart_of_account_id'] ?? '?'));
        }

        if (! $account->is_postable) {
            throw JournalPostingException::nonPostableAccount($account->code);
        }

        return [
            'chart_of_account_id' => $account->id,
            'debit' => number_format((float) ($line['debit'] ?? 0), 2, '.', ''),
            'credit' => number_format((float) ($line['credit'] ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @param  array<int, array{chart_of_account_id: int, debit: string, credit: string}>  $resolvedLines
     */
    private function assertBalanced(array $resolvedLines): void
    {
        $totalDebitCents = 0;
        $totalCreditCents = 0;

        foreach ($resolvedLines as $line) {
            $totalDebitCents += (int) round(((float) $line['debit']) * 100);
            $totalCreditCents += (int) round(((float) $line['credit']) * 100);
        }

        if ($totalDebitCents !== $totalCreditCents) {
            throw JournalPostingException::unbalanced(
                number_format($totalDebitCents / 100, 2, '.', ''),
                number_format($totalCreditCents / 100, 2, '.', ''),
            );
        }
    }

    private function assertPeriodOpen(int $branchId, Carbon $entryDate): void
    {
        $period = AccountingPeriod::query()
            ->where('branch_id', $branchId)
            ->whereDate('period_start', '<=', $entryDate)
            ->whereDate('period_end', '>=', $entryDate)
            ->first();

        if ($period !== null && ! $period->isOpen()) {
            throw JournalPostingException::periodClosed((string) $branchId, $entryDate->toDateString());
        }
    }

    private function findByIdempotencyKey(string $key): ?JournalEntry
    {
        $record = DB::table('journal_entry_idempotency_keys')
            ->where('idempotency_key', $key)
            ->first();

        if ($record === null) {
            return null;
        }

        return JournalEntry::query()->with('lines')->find($record->journal_entry_id);
    }

    private function claimIdempotencyKey(string $key, JournalEntry $entry): void
    {
        try {
            DB::table('journal_entry_idempotency_keys')->insert([
                'idempotency_key' => $key,
                'journal_entry_id' => $entry->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            // Unique constraint race: another concurrent request claimed this
            // key first. Roll back this entry's creation by letting the
            // outer DB::transaction fail, so only the first writer wins.
            throw $exception;
        }
    }
}
