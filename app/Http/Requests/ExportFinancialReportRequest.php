<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportFinancialReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('laporan_keuangan.read') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report_kind' => ['required', Rule::in(['neraca', 'laba_rugi', 'arus_kas', 'calk'])],
            'basis' => ['required', Rule::in(['sak_ep', 'sak_emkm'])],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')],
            'as_of_date' => ['required_if:report_kind,neraca,calk', 'nullable', 'date'],
            'period_start' => ['required_if:report_kind,laba_rugi,arus_kas', 'nullable', 'date'],
            'period_end' => ['required_if:report_kind,laba_rugi,arus_kas', 'nullable', 'date', 'after_or_equal:period_start'],
        ];
    }
}
