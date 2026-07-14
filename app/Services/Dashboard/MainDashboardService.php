<?php

namespace App\Services\Dashboard;

use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;

/**
 * KPI Dashboard Utama (PRD §15) — semua angka diturunkan langsung dari
 * ledger/rekening operasional (single source of truth, LED-09), bukan
 * dihitung/disimpan terpisah. `branchId = null` berarti konsolidasi seluruh
 * cabang (hanya diizinkan untuk user Cabang Scope All Branch — ditegakkan
 * di controller, bukan di sini).
 */
class MainDashboardService
{
    public function summary(?int $branchId = null): array
    {
        $membersByType = Member::query()
            ->join('member_types', 'members.member_type_id', '=', 'member_types.id')
            ->when($branchId, fn ($q) => $q->where('members.branch_id', $branchId))
            ->selectRaw('member_types.name as type_name, count(*) as total')
            ->groupBy('member_types.name')
            ->get();

        $savingsByProduct = SavingsAccount::query()
            ->where('savings_accounts.status', 'aktif')
            ->join('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->when($branchId, fn ($q) => $q->where('savings_accounts.branch_id', $branchId))
            ->selectRaw('savings_products.name as product_name, sum(balance) as total')
            ->groupBy('savings_products.name')
            ->get();

        $loanBase = Loan::query()
            ->where('status', 'dicairkan')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $totalLoanOutstanding = (float) (clone $loanBase)->sum('principal_amount');
        $nplOutstanding = (float) (clone $loanBase)->whereIn('collectibility', ['kurang_lancar', 'diragukan', 'macet'])->sum('principal_amount');
        $nplRatio = $totalLoanOutstanding > 0 ? round($nplOutstanding / $totalLoanOutstanding * 100, 2) : 0.0;

        return [
            'members_by_type' => $membersByType,
            'total_members' => (int) $membersByType->sum('total'),
            'savings_by_product' => $savingsByProduct,
            'total_savings' => (float) $savingsByProduct->sum('total'),
            'total_loan_outstanding' => $totalLoanOutstanding,
            'npl_ratio' => $nplRatio,
            'shu_running' => $this->calculateRunningShu($branchId),
        ];
    }

    /**
     * SHU berjalan = Pendapatan − Beban, dihitung dari saldo bersih
     * journal_lines per tipe akun (bukan angka tersimpan terpisah).
     * Pendapatan dihitung (kredit − debit); Beban dihitung (debit − kredit)
     * — ini otomatis benar walau ada akun kontra (mis. Retur Penjualan yang
     * bersaldo normal debit di dalam kelompok PENDAPATAN).
     */
    private function calculateRunningShu(?int $branchId): float
    {
        $baseQuery = fn () => JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId));

        $pendapatan = (float) $baseQuery()
            ->where('chart_of_accounts.type', 'PENDAPATAN')
            ->selectRaw('COALESCE(SUM(journal_lines.credit - journal_lines.debit), 0) as net')
            ->value('net');

        $beban = (float) $baseQuery()
            ->where('chart_of_accounts.type', 'BEBAN')
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net')
            ->value('net');

        return round($pendapatan - $beban, 2);
    }
}
