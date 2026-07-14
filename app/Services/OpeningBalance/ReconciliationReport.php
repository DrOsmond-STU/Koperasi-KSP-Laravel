<?php

namespace App\Services\OpeningBalance;

class ReconciliationReport
{
    /**
     * @param  array<int, array{chart_of_account_id: int, sub_module_total: float, coa_amount: float}>  $savingsDiscrepancies
     * @param  array<int, array{chart_of_account_id: int, sub_module_total: float, coa_amount: float}>  $loansDiscrepancies
     */
    public function __construct(
        public readonly float $coaDebitTotal,
        public readonly float $coaCreditTotal,
        public readonly array $savingsDiscrepancies,
        public readonly array $loansDiscrepancies,
    ) {}

    public function isCoaBalanced(): bool
    {
        return bccomp((string) $this->coaDebitTotal, (string) $this->coaCreditTotal, 2) === 0;
    }

    public function isFullyBalanced(): bool
    {
        return $this->isCoaBalanced()
            && $this->savingsDiscrepancies === []
            && $this->loansDiscrepancies === [];
    }
}
