<?php

namespace App\Services\FixedAsset;

use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\FixedAssetDisposal;
use Illuminate\Support\Collection;

/**
 * Laporan Aktiva Tetap (PRD §14.3, Task 3.12). Read-model queries only —
 * Akumulasi Penyusutan & Nilai Buku are always derived from
 * fixed_asset_depreciation_entries via FixedAsset::accumulatedDepreciation()/
 * bookValue(), never a cached column, so this report and Kartu Penyusutan
 * can never disagree (DEP-06).
 */
class FixedAssetReportService
{
    /**
     * Daftar Aktiva Tetap — per cabang (BranchScope applies automatically)
     * atau konsolidasi (an Admin Sistem/pengurus with "All Branch" scope
     * simply sees every branch's assets, same as everywhere else BranchScope
     * is relied on instead of a manual branch_id filter when $branchId is null).
     *
     * @return Collection<int, array{asset: FixedAsset, accumulated_depreciation: string, book_value: string}>
     */
    public function daftarAktivaTetap(?int $branchId): Collection
    {
        return FixedAsset::query()
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->with('category')
            ->orderBy('code')
            ->get()
            ->map(fn (FixedAsset $asset) => [
                'asset' => $asset,
                'accumulated_depreciation' => $asset->accumulatedDepreciation(),
                'book_value' => $asset->bookValue(),
            ]);
    }

    /**
     * Kartu Penyusutan per Aset — rincian bulanan, kumulatif berjalan.
     */
    public function kartuPenyusutan(int $fixedAssetId): Collection
    {
        return FixedAssetDepreciationEntry::query()
            ->where('fixed_asset_id', $fixedAssetId)
            ->orderBy('period')
            ->get();
    }

    /**
     * Ringkasan Penyusutan per Periode, dikelompokkan per kategori — untuk
     * input Laba Rugi.
     *
     * @return Collection<int, array{category_name: string, total_depreciation: string}>
     */
    public function ringkasanPenyusutanPerPeriode(string $period, ?int $branchId): Collection
    {
        return FixedAssetDepreciationEntry::query()
            ->where('period', $period)
            ->whereHas('fixedAsset', fn ($query) => $query->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId)))
            ->with('fixedAsset.category')
            ->get()
            ->groupBy(fn (FixedAssetDepreciationEntry $entry) => $entry->fixedAsset->category->name)
            ->map(fn (Collection $entries, string $categoryName) => [
                'category_name' => $categoryName,
                'total_depreciation' => $entries->reduce(fn (string $carry, FixedAssetDepreciationEntry $entry) => bcadd($carry, (string) $entry->depreciation_amount, 2), '0.00'),
            ])
            ->values();
    }

    /**
     * Laporan Pelepasan Aktiva — laba/rugi pelepasan.
     */
    public function laporanPelepasan(?int $branchId): Collection
    {
        return FixedAssetDisposal::query()
            ->whereHas('fixedAsset', fn ($query) => $query->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId)))
            ->with('fixedAsset')
            ->orderByDesc('disposal_date')
            ->get();
    }
}
