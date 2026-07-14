<?php

namespace App\Services\BusinessUnit;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitTransaction;
use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalEngine;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi Unit Usaha Lain (PRD §12, Task 2.4). Pendapatan: Dr Kas, Cr
 * Pendapatan unit. Beban: Dr Beban unit, Cr Kas.
 */
class BusinessUnitService
{
    private const DEFAULT_CASH_ACCOUNT_CODE = '1101';

    public function __construct(private readonly JournalEngine $journalEngine) {}

    public function record(
        BusinessUnit $unit,
        string $type,
        float $amount,
        int $branchId,
        int $createdBy,
        ?string $description = null,
    ): BusinessUnitTransaction {
        return DB::transaction(function () use ($unit, $type, $amount, $branchId, $createdBy, $description) {
            $cashAccountId = $this->cashAccountId();
            $unitAccountId = $type === 'pendapatan' ? $unit->coa_revenue_account_id : $unit->coa_expense_account_id;

            $lines = $type === 'pendapatan'
                ? [
                    ['chart_of_account_id' => $cashAccountId, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $unitAccountId, 'debit' => 0, 'credit' => $amount],
                ]
                : [
                    ['chart_of_account_id' => $unitAccountId, 'debit' => $amount, 'credit' => 0],
                    ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => $amount],
                ];

            $entry = $this->journalEngine->post([
                'branch_id' => $branchId,
                'entry_date' => now()->toDateString(),
                'description' => $description ?? "{$type} unit usaha {$unit->name}",
                'created_by' => $createdBy,
                'source' => $unit,
                'lines' => $lines,
            ]);

            return BusinessUnitTransaction::query()->create([
                'business_unit_id' => $unit->id,
                'branch_id' => $branchId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'journal_entry_id' => $entry->id,
                'created_by' => $createdBy,
            ]);
        });
    }

    private function cashAccountId(): int
    {
        return ChartOfAccount::query()->where('code', self::DEFAULT_CASH_ACCOUNT_CODE)->value('id');
    }
}
