<?php

namespace App\Http\Requests;

use App\Services\Reporting\ReportTypeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateCustomReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('laporan_kustom.create') ?? false;
    }

    /**
     * Column whitelist enforcement mirrors ReportBuilderService (RPT-10) —
     * this is a UX-level early rejection, the service re-checks
     * independently regardless of what passes here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reportType = (string) $this->input('report_type');
        $allowedColumns = array_keys(ReportTypeRegistry::columnsFor($reportType));

        return [
            'report_type' => ['required', Rule::in(array_keys(ReportTypeRegistry::definitions()))],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => [Rule::in($allowedColumns)],
            'save_as_template' => ['nullable', 'string', 'max:150'],
            'export_format' => ['nullable', Rule::in(['pdf', 'xlsx'])],

            'filters' => ['nullable', 'array'],
            'filters.product_id' => ['required_if:report_type,persediaan_kartu', Rule::exists('products', 'id')],
            'filters.branch_id' => ['nullable', Rule::exists('branches', 'id')],
            'filters.period_start' => ['required_if:report_type,persediaan_kartu', 'date'],
            'filters.period_end' => ['required_if:report_type,persediaan_kartu', 'date', 'after_or_equal:filters.period_start'],
            'filters.fixed_asset_id' => ['required_if:report_type,aktiva_tetap_kartu_penyusutan', Rule::exists('fixed_assets', 'id')],
        ];
    }
}
