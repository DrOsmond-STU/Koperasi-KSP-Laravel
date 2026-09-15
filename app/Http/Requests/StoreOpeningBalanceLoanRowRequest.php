<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceLoanRowRequest extends FormRequest
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
     * Field sama dengan validator CSV Pinjaman (OpeningBalanceImportService::validateLoans).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', Rule::exists('members', 'id')],
            'loan_product_id' => ['required', Rule::exists('loan_products', 'id')],
            'external_loan_number' => ['nullable', 'string', 'max:50'],
            'disbursement_date' => ['required', 'date'],
            'original_principal' => ['required', 'numeric', 'min:0'],
            'outstanding_principal' => ['required', 'numeric', 'min:0'],
            'outstanding_interest' => ['nullable', 'numeric', 'min:0'],
            'tenor_days' => ['required', 'integer', 'min:1'],
            'remaining_tenor_days' => ['required', 'integer', 'min:0'],
            'next_installment_number' => ['required', 'integer', 'min:1'],
            'next_due_date' => ['required', 'date'],
            'collectibility' => ['required', Rule::in(['lancar', 'kurang_lancar', 'diragukan', 'macet'])],
        ];
    }
}
