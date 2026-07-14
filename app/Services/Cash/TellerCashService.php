<?php

namespace App\Services\Cash;

use App\Models\CashCategory;
use App\Models\ChartOfAccount;
use App\Models\TellerCashTransaction;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Kas Masuk/Keluar Teller (PRD §11, Task 2.1) — kategori dinamis, masing-
 * masing dipetakan ke satu akun COA lawan. Kas Masuk: Dr Kas, Cr Kategori.
 * Kas Keluar: Dr Kategori, Cr Kas.
 */
class TellerCashService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function record(
        CashCategory $category,
        float $amount,
        int $branchId,
        int $createdBy,
        ?string $description = null,
        ?ChartOfAccount $cashAccount = null,
        ?string $idempotencyKey = null,
    ): TellerCashTransaction {
        $cashAccount ??= $this->defaultCashAccount();

        return DB::transaction(function () use ($category, $amount, $branchId, $createdBy, $description, $cashAccount, $idempotencyKey) {
            $lines = $category->isMasuk()
                ? [
                    ['chart_of_account_id' => $cashAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $category->coa_account_id, 'debit' => 0, 'credit' => $amount],
                ]
                : [
                    ['chart_of_account_id' => $category->coa_account_id, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $amount],
                ];

            $entry = $this->journalEngine->post([
                'branch_id' => $branchId,
                'entry_date' => now()->toDateString(),
                'description' => $description ?? "Kas {$category->type}: {$category->name}",
                'created_by' => $createdBy,
                'idempotency_key' => $idempotencyKey,
                'lines' => $lines,
            ]);

            return TellerCashTransaction::query()->create([
                'branch_id' => $branchId,
                'cash_category_id' => $category->id,
                'cash_account_id' => $cashAccount->id,
                'amount' => $amount,
                'description' => $description,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * @return array<int, array{account_code: string, account_name: string, debit: float, credit: float}>
     */
    public function previewLines(CashCategory $category, float $amount, ?ChartOfAccount $cashAccount = null): array
    {
        $cashAccount ??= $this->defaultCashAccount();
        $categoryAccount = $category->account;

        return $category->isMasuk()
            ? [
                ['account_code' => $cashAccount->code, 'account_name' => $cashAccount->name, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $categoryAccount->code, 'account_name' => $categoryAccount->name, 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['account_code' => $categoryAccount->code, 'account_name' => $categoryAccount->name, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $cashAccount->code, 'account_name' => $cashAccount->name, 'debit' => 0, 'credit' => $amount],
            ];
    }

    private function defaultCashAccount(): ChartOfAccount
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->firstOrFail();
    }
}
