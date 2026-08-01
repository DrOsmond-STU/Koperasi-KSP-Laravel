<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetributionTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:retribution_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'coa_revenue_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('is_postable', true)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
