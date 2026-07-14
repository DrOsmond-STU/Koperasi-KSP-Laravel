<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeeTypeRequest extends FormRequest
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
        $postableAccount = fn () => Rule::exists('chart_of_accounts', 'id')->where('is_postable', true);

        return [
            'code' => ['required', 'string', 'max:30', 'unique:fee_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'unit_type' => ['required', Rule::in(['flat', 'per_meter', 'per_m2'])],
            'tariff' => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', Rule::in(['bulanan', 'mingguan'])],
            'coa_receivable_account_id' => ['required', $postableAccount()],
            'coa_revenue_account_id' => ['required', $postableAccount()],
        ];
    }
}
