<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedAssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('aktiva_tetap.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `residual_value` <= `acquisition_cost` covers 06_TESTING.md DEP-07 —
     * rejected at creation, not later when the Depreciation Engine runs.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'fixed_asset_category_id' => ['required', Rule::exists('fixed_asset_categories', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:30', 'unique:fixed_assets,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'ownership_document_number' => ['nullable', 'string', 'max:100'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'residual_value' => ['required', 'numeric', 'min:0', 'lte:acquisition_cost'],
            'useful_life_months' => ['required', 'integer', 'min:1'],
            'depreciation_method' => ['required', Rule::in(['garis_lurus', 'saldo_menurun'])],
            'depreciation_rate_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100', 'required_if:depreciation_method,saldo_menurun'],
            'payment_method' => ['required', Rule::in(['tunai', 'kredit'])],
            'supplier_id' => ['nullable', 'required_if:payment_method,kredit', Rule::exists('suppliers', 'id')->where('is_active', true)],
        ];
    }
}
