<?php

namespace App\Services\Accounting;

use App\Models\Member;
use App\Models\ShuAllocationCategory;
use App\Services\Reporting\FinancialReportEngine;
use Illuminate\Support\Collection;

/**
 * Simulasi/perhitungan distribusi SHU (PRD §5.4, Task 5.4, UU 25/1992).
 * Total SHU tahun buku diambil dari FinancialReportEngine::labaRugi() —
 * satu sumber angka yang sama dengan Laporan Keuangan (LED-09). Alokasi
 * per kategori mengikuti `shu_allocation_categories` (AD/ART, bukan
 * hardcode); porsi "Jasa Anggota" dipecah lebih lanjut proporsional
 * terhadap saldo simpanan aktif anggota yang jenis keanggotaannya
 * `counts_toward_shu = true` (RPT-08) — anggota jenis lain (mis. Calon
 * Anggota) tidak diikutkan.
 */
class ShuService
{
    public function __construct(private readonly FinancialReportEngine $financialReportEngine) {}

    /**
     * @return array{
     *     year: int, total_shu: string, total_percentage: string, is_fully_allocated: bool,
     *     allocations: Collection, member_shares: Collection,
     * }
     */
    public function simulate(?int $branchId, int $year): array
    {
        $labaRugi = $this->financialReportEngine->labaRugi($branchId, "{$year}-01-01", "{$year}-12-31");
        $totalShu = $labaRugi['shu'];

        $categories = ShuAllocationCategory::query()->where('is_active', true)->get();
        $totalPercentage = $categories->reduce(fn (string $carry, ShuAllocationCategory $category) => bcadd($carry, (string) $category->percentage, 2), '0.00');

        $allocations = $categories->map(fn (ShuAllocationCategory $category) => [
            'category' => $category,
            'amount' => bcmul($totalShu, bcdiv((string) $category->percentage, '100', 6), 2),
        ]);

        $jasaAnggotaCategory = $categories->firstWhere('is_jasa_anggota', true);
        $memberShares = collect();

        if ($jasaAnggotaCategory !== null) {
            $jasaAnggotaAmount = $allocations->firstWhere('category.id', $jasaAnggotaCategory->id)['amount'];
            $memberShares = $this->distributeJasaAnggota($branchId, $jasaAnggotaAmount);
        }

        return [
            'year' => $year,
            'total_shu' => $totalShu,
            'total_percentage' => $totalPercentage,
            'is_fully_allocated' => bccomp($totalPercentage, '100.00', 2) === 0,
            'allocations' => $allocations,
            'member_shares' => $memberShares,
        ];
    }

    /**
     * @return Collection<int, array{member: Member, savings_balance: string, share: string}>
     */
    private function distributeJasaAnggota(?int $branchId, string $jasaAnggotaAmount): Collection
    {
        $eligibleMembers = Member::query()
            ->whereHas('memberType', fn ($query) => $query->where('counts_toward_shu', true))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->with(['savingsAccounts' => fn ($query) => $query->where('status', 'aktif')])
            ->get();

        $balances = $eligibleMembers->mapWithKeys(fn (Member $member) => [
            $member->id => (string) $member->savingsAccounts->reduce(fn (string $carry, $account) => bcadd($carry, (string) $account->balance, 2), '0.00'),
        ]);

        $totalEligibleSavings = $balances->reduce(fn (string $carry, string $balance) => bcadd($carry, $balance, 2), '0.00');

        return $eligibleMembers->map(function (Member $member) use ($balances, $totalEligibleSavings, $jasaAnggotaAmount) {
            $balance = $balances[$member->id];
            $share = bccomp($totalEligibleSavings, '0', 2) > 0
                ? bcmul($jasaAnggotaAmount, bcdiv($balance, $totalEligibleSavings, 10), 2)
                : '0.00';

            return [
                'member' => $member,
                'savings_balance' => $balance,
                'share' => $share,
            ];
        });
    }
}
