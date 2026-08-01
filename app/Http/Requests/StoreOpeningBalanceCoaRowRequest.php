<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceCoaRowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('saldo_awal.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Field sama dengan validator CSV COA (OpeningBalanceImportService::validateCoa).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'chart_of_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('is_postable', true)],
            'position' => ['required', Rule::in(['debit', 'kredit'])],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
