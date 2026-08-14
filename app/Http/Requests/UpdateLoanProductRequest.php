<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ubah Produk Pinjaman — lihat UpdateSavingsProductRequest untuk alasan
 * `code` diabaikan terhadap dirinya sendiri dan tarif jasa tidak ikut diubah
 * di sini.
 */
class UpdateLoanProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postableAccount = fn () => Rule::exists('chart_of_accounts', 'id')->where('is_postable', true);
        $product = $this->route('loanProduct');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('loan_products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'min_plafon' => ['required', 'numeric', 'min:0'],
            'max_plafon' => ['required', 'numeric', 'gte:min_plafon'],
            'min_tenor_months' => ['required', 'integer', 'min:1'],
            'max_tenor_months' => ['required', 'integer', 'gte:min_tenor_months'],
            'calculation_method' => ['required', Rule::in(['flat', 'efektif', 'anuitas'])],
            'provision_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penalty_percentage_per_day' => ['nullable', 'numeric', 'min:0'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'coa_receivable_account_id' => ['required', $postableAccount()],
            'coa_interest_income_account_id' => ['required', $postableAccount()],
            'coa_provision_income_account_id' => ['required', $postableAccount()],
            'coa_penalty_receivable_account_id' => ['required', $postableAccount()],
        ];
    }
}
