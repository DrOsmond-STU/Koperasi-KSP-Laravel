<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('unit_usaha.create') ?? false;
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
            'code' => ['required', 'string', 'max:30', 'unique:business_units,code'],
            'name' => ['required', 'string', 'max:150'],
            'coa_revenue_account_id' => ['required', $postableAccount()],
            'coa_expense_account_id' => ['required', $postableAccount()],
        ];
    }
}
