<?php

namespace App\Services\Reporting;

use App\Exceptions\Reporting\ReportBuilderException;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\FixedAsset\FixedAssetReportService;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Report Builder Dinamis (PRD §17, Task 4.5). `generate()` is the single
 * choke point every export/preview goes through — it always re-validates
 * $selectedColumns against ReportTypeRegistry, even for a saved template
 * (RPT-10/RPT-11: a template's stored columns are themselves re-checked,
 * never assumed still valid if the registry ever changes).
 */
class ReportBuilderService
{
    public function __construct(
        private readonly InventoryReportService $inventoryReportService,
        private readonly FixedAssetReportService $fixedAssetReportService,
    ) {}

    /**
     * @param  array<int, string>  $selectedColumns
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function generate(string $reportType, array $selectedColumns, array $filters): Collection
    {
        if (! ReportTypeRegistry::exists($reportType)) {
            throw ReportBuilderException::unknownReportType($reportType);
        }

        $whitelist = array_keys(ReportTypeRegistry::columnsFor($reportType));
        $invalid = array_diff($selectedColumns, $whitelist);

        if (count($invalid) > 0) {
            throw ReportBuilderException::columnsNotWhitelisted(array_values($invalid));
        }

        return $this->fetchRawRows($reportType, $filters)
            ->map(function ($row) use ($selectedColumns) {
                $array = $row instanceof Model ? $row->toArray() : (array) $row;

                return collect($array)->only($selectedColumns)->all();
            });
    }

    public function saveTemplate(
        string $name,
        string $reportType,
        array $columns,
        array $filters,
        User $user,
        ?int $sharedRoleId = null,
    ): ReportTemplate {
        if (! ReportTypeRegistry::exists($reportType)) {
            throw ReportBuilderException::unknownReportType($reportType);
        }

        $whitelist = array_keys(ReportTypeRegistry::columnsFor($reportType));
        $invalid = array_diff($columns, $whitelist);

        if (count($invalid) > 0) {
            throw ReportBuilderException::columnsNotWhitelisted(array_values($invalid));
        }

        return ReportTemplate::query()->create([
            'name' => $name,
            'report_type' => $reportType,
            'columns' => $columns,
            'filters' => $filters,
            'created_by' => $user->id,
            'shared_role_id' => $sharedRoleId,
        ]);
    }

    /**
     * @return Collection<int, ReportTemplate>
     */
    public function templatesAvailableTo(User $user): Collection
    {
        $roleIds = $user->roles()->pluck('roles.id');

        return ReportTemplate::query()
            ->where('created_by', $user->id)
            ->orWhereIn('shared_role_id', $roleIds)
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function fetchRawRows(string $reportType, array $filters): Collection
    {
        return match ($reportType) {
            'persediaan_kartu' => $this->inventoryReportService->kartuRincian(
                (int) $filters['product_id'],
                isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                $filters['period_start'],
                $filters['period_end'],
            ),
            'aktiva_tetap_kartu_penyusutan' => $this->fixedAssetReportService->kartuPenyusutan((int) $filters['fixed_asset_id']),
            default => collect(),
        };
    }
}
