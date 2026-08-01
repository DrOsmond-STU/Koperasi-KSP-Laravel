<?php

namespace App\Http\Requests;

use App\Models\RetributionType;
use App\Services\Reporting\ReportTypeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRetributionReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('retribusi_upf.read') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Whitelist kolom digabung: statis dari ReportTypeRegistry +
     * "retribusi_line_{id}" dinamis per jenis retribusi aktif SAAT request
     * ini — bukan lewat ReportBuilderService::generate() (yang hanya
     * mengenal whitelist statis), karena kolom dinamis di sini murni milik
     * tab Laporan pada modul Retribusi (UPF), bukan Report Builder umum.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => [Rule::in($this->allowedColumns())],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedColumns(): array
    {
        $static = array_keys(ReportTypeRegistry::columnsFor('retribusi_upf'));
        $dynamic = RetributionType::query()->where('is_active', true)->get()->map(fn ($type) => "retribusi_line_{$type->id}")->all();

        return [...$static, ...$dynamic];
    }
}
