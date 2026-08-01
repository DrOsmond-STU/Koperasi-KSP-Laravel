<?php

namespace App\Services\OpeningBalance;

use App\Models\OpeningBalanceBatch;

/**
 * Checks the invariants PRD §9 requires before a batch can be locked:
 * total debit = total credit across opening_balance_coa, AND the savings/
 * loans sub-modules each sum to the matching COA account balance (per the
 * product's own COA mapping, since different products can point to
 * different liability/receivable accounts).
 */
class OpeningBalanceReconciliationService
{
    public function reconcile(OpeningBalanceBatch $batch): ReconciliationReport
    {
        $coaDebitTotal = (float) $batch->coaLines()->where('position', 'debit')->sum('amount');
        $coaCreditTotal = (float) $batch->coaLines()->where('position', 'kredit')->sum('amount');

        return new ReconciliationReport(
            coaDebitTotal: $coaDebitTotal,
            coaCreditTotal: $coaCreditTotal,
            savingsDiscrepancies: $this->reconcileSavingsToCoa($batch),
            loansDiscrepancies: $this->reconcileLoansToCoa($batch),
            upfDiscrepancies: $this->reconcileUpfToCoa($batch),
            stockDiscrepancies: $this->reconcileStockToCoa($batch),
        );
    }

    /**
     * @return array<int, array{chart_of_account_id: int, sub_module_total: float, coa_amount: float}>
     */
    private function reconcileSavingsToCoa(OpeningBalanceBatch $batch): array
    {
        $grouped = $batch->savings()->with('savingsProduct')->get()
            ->groupBy(fn ($row) => $row->savingsProduct->coa_liability_account_id);

        $discrepancies = [];

        foreach ($grouped as $accountId => $rows) {
            $subModuleTotal = (float) $rows->sum('balance');
            $coaAmount = (float) $batch->coaLines()
                ->where('chart_of_account_id', $accountId)
                ->where('position', 'kredit')
                ->sum('amount');

            if (bccomp((string) $subModuleTotal, (string) $coaAmount, 2) !== 0) {
                $discrepancies[] = [
                    'chart_of_account_id' => $accountId,
                    'sub_module_total' => $subModuleTotal,
                    'coa_amount' => $coaAmount,
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * @return array<int, array{chart_of_account_id: int, sub_module_total: float, coa_amount: float}>
     */
    private function reconcileLoansToCoa(OpeningBalanceBatch $batch): array
    {
        $grouped = $batch->loans()->with('loanProduct')->get()
            ->groupBy(fn ($row) => $row->loanProduct->coa_receivable_account_id);

        $discrepancies = [];

        foreach ($grouped as $accountId => $rows) {
            $subModuleTotal = (float) $rows->sum('outstanding_principal');
            $coaAmount = (float) $batch->coaLines()
                ->where('chart_of_account_id', $accountId)
                ->where('position', 'debit')
                ->sum('amount');

            if (bccomp((string) $subModuleTotal, (string) $coaAmount, 2) !== 0) {
                $discrepancies[] = [
                    'chart_of_account_id' => $accountId,
                    'sub_module_total' => $subModuleTotal,
                    'coa_amount' => $coaAmount,
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Saldo Awal UPF v2: saldo pendapatan per jenis retribusi, ditagih ke
     * akun pendapatan (`RetributionType.coa_revenue_account_id`) — pola
     * sama seperti reconcileSavingsToCoa()/reconcileLoansToCoa(), akun
     * pendapatan berbeda jenis (Laba Rugi, bukan Neraca) tapi mekanisme
     * pencocokan totalnya identik (posisi kredit, akun pendapatan naik lewat kredit).
     *
     * @return array<int, array{chart_of_account_id: int, sub_module_total: float, coa_amount: float}>
     */
    private function reconcileUpfToCoa(OpeningBalanceBatch $batch): array
    {
        $grouped = $batch->upf()->with('retributionType')->get()
            ->groupBy(fn ($row) => $row->retributionType->coa_revenue_account_id);

        $discrepancies = [];

        foreach ($grouped as $accountId => $rows) {
            $subModuleTotal = (float) $rows->sum('amount');
            $coaAmount = (float) $batch->coaLines()
                ->where('chart_of_account_id', $accountId)
                ->where('position', 'kredit')
                ->sum('amount');

            if (bccomp((string) $subModuleTotal, (string) $coaAmount, 2) !== 0) {
                $discrepancies[] = [
                    'chart_of_account_id' => $accountId,
                    'sub_module_total' => $subModuleTotal,
                    'coa_amount' => $coaAmount,
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Saldo Awal Persediaan — nilai (qty x harga) per barang, ditagih ke
     * akun persediaan (`Product.coa_inventory_account_id`) — akun Aset
     * (Neraca, posisi Debit), pola pencocokan totalnya identik dengan
     * reconcileLoansToCoa() (akun Piutang, sama-sama Aset debit-normal).
     *
     * @return array<int, array{chart_of_account_id: int, sub_module_total: float, coa_amount: float}>
     */
    private function reconcileStockToCoa(OpeningBalanceBatch $batch): array
    {
        $grouped = $batch->stock()->with('product')->get()
            ->groupBy(fn ($row) => $row->product->coa_inventory_account_id);

        $discrepancies = [];

        foreach ($grouped as $accountId => $rows) {
            $subModuleTotal = (float) $rows->sum(fn ($row) => (float) $row->qty * (float) $row->unit_cost);
            $coaAmount = (float) $batch->coaLines()
                ->where('chart_of_account_id', $accountId)
                ->where('position', 'debit')
                ->sum('amount');

            if (bccomp((string) $subModuleTotal, (string) $coaAmount, 2) !== 0) {
                $discrepancies[] = [
                    'chart_of_account_id' => $accountId,
                    'sub_module_total' => $subModuleTotal,
                    'coa_amount' => $coaAmount,
                ];
            }
        }

        return $discrepancies;
    }
}
