<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceInstallmentRowRequest extends FormRequest
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
     * `opening_balance_loan_id` dipilih dari dropdown (pinjaman yang sudah
     * ada di batch ini) — dibatasi ke batch yang sama persis seperti aturan
     * OB-09 di validator CSV (no_pinjaman_lama harus sudah ada di batch ini).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_balance_loan_id' => [
                'required',
                Rule::exists('opening_balance_loans', 'id')->where('opening_balance_batch_id', $this->route('batch')?->id),
            ],
            'installment_number' => ['required', 'integer', 'min:1'],
            'due_date' => ['required', 'date'],
            'principal_amount' => ['required', 'numeric', 'min:0'],
            'interest_amount' => ['required', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['belum_bayar', 'sebagian'])],
        ];
    }
}
