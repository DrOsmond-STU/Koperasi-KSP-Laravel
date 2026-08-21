<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanProductRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:loan_products,code'],
            'name' => ['required', 'string', 'max:150'],
            'min_plafon' => ['required', 'numeric', 'min:0'],
            'max_plafon' => ['required', 'numeric', 'gte:min_plafon'],
            'min_tenor_days' => ['required', 'integer', 'min:1'],
            'max_tenor_days' => ['required', 'integer', 'gte:min_tenor_days'],
            'calculation_method' => ['required', Rule::in(['flat', 'efektif', 'anuitas'])],
            'provision_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penalty_percentage_per_day' => ['nullable', 'numeric', 'min:0'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'coa_receivable_account_id' => ['required', $postableAccount()],
            'coa_interest_income_account_id' => ['required', $postableAccount()],
            'coa_provision_income_account_id' => ['required', $postableAccount()],
            'coa_penalty_receivable_account_id' => ['required', $postableAccount()],
            'initial_rate_percentage' => ['required', 'numeric', 'min:0'],
            'initial_rate_effective_from' => ['nullable', 'date'],
        ];
    }
}
