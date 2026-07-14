<?php

namespace App\Services\Dashboard;

use App\Models\JournalLine;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\SavingsProduct;

/**
 * Dashboard RAT (PRD §15, Task 2.5) — ringkasan tahun buku berjalan untuk
 * persiapan RAT. Perhitungan SHU di sini mengikuti pola yang sama dengan
 * MainDashboardService::calculateRunningShu(), tapi dibatasi ke tahun buku
 * berjalan (bukan all-time) — lihat §5.4 di 01_PRD.md untuk simulasi
 * alokasi SHU penuh (dana cadangan/jasa anggota/dst), yang menyusul di
 * Fase 5 setelah modul SHU dibangun.
 */
class RatDashboardService
{
    public function summary(?int $branchId = null, ?int $year = null): array
    {
        $year ??= (int) now()->year;

        $baseQuery = fn () => JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.chart_of_account_id', '=', 'chart_of_accounts.id')
            ->whereYear('journal_entries.entry_date', $year)
            ->when($branchId, fn ($q) => $q->where('journal_entries.branch_id', $branchId));

        $pendapatan = (float) $baseQuery()
            ->where('chart_of_accounts.type', 'PENDAPATAN')
            ->selectRaw('COALESCE(SUM(journal_lines.credit - journal_lines.debit), 0) as net')
            ->value('net');

        $beban = (float) $baseQuery()
            ->where('chart_of_accounts.type', 'BEBAN')
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net')
            ->value('net');

        $memberGrowth = Member::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereYear('joined_at', $year)
            ->selectRaw('MONTH(joined_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $totalMembers = Member::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();

        $hasJournalActivity = $baseQuery()->exists();

        return [
            'year' => $year,
            'pendapatan' => $pendapatan,
            'beban' => $beban,
            'shu' => round($pendapatan - $beban, 2),
            'member_growth' => $memberGrowth,
            'total_members' => $totalMembers,
            'readiness_checklist' => [
                'Neraca & Laba Rugi siap disusun' => $hasJournalActivity,
                'Data anggota tersedia' => $totalMembers > 0,
                'Produk simpanan aktif tersedia' => SavingsProduct::query()->where('is_active', true)->exists(),
                'Produk pinjaman aktif tersedia' => LoanProduct::query()->where('is_active', true)->exists(),
            ],
        ];
    }
}
