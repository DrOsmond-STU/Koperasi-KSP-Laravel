<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('chart_of_account.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['ASET', 'LIABILITAS', 'EKUITAS', 'PENDAPATAN', 'BEBAN'])],
            'group' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['DEBIT', 'KREDIT'])],
            'is_postable' => ['nullable', 'boolean'],
            'parent_code' => ['nullable', 'string', Rule::exists('chart_of_accounts', 'code')],
            'statement' => ['required', Rule::in(['NERACA', 'LABA_RUGI'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
