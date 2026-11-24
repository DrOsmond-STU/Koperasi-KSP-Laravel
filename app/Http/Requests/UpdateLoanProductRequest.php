<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mirror StoreLoanProductRequest tapi tanpa `initial_rate_*` — perubahan
 * tarif jasa berjalan lewat menu Tarif Parameter (AddLoanRateHistoryRequest)
 * karena tarif bersifat versi-per-tanggal (non-retroaktif, PRD §7.2).
 * `code` unique-nya ignoring the current product's id supaya bisa disimpan
 * ulang tanpa mengubah kode; `is_active` boleh di-toggle dari sini.
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

        /** @var LoanProduct $product */
        $product = $this->route('loanProduct');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('loan_products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:150'],
            'min_plafon' => ['required', 'numeric', 'min:0'],
            'max_plafon' => ['required', 'numeric', 'gte:min_plafon'],
            'min_tenor_days' => ['required', 'integer', 'min:1'],
            'max_tenor_days' => ['required', 'integer', 'gte:min_tenor_days'],
            // 'hari' (ditagih harian, mis. anggota pasar) atau 'bulan'
            // (potong gaji bulanan, mis. piutang karyawan) — lihat
            // LoanProduct::usesDailyTenor().
            'tenor_unit' => ['required', Rule::in(['hari', 'bulan'])],
            'calculation_method' => ['required', Rule::in(['flat', 'efektif', 'anuitas'])],
            'provision_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penalty_percentage_per_day' => ['nullable', 'numeric', 'min:0'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'coa_receivable_account_id' => ['required', $postableAccount()],
            'coa_interest_income_account_id' => ['required', $postableAccount()],
            'coa_provision_income_account_id' => ['required', $postableAccount()],
            'coa_penalty_receivable_account_id' => ['required', $postableAccount()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Boolean toggle datang sebagai checkbox → kalau kosong artinya
     * non-aktif; kalau ada nilai apapun dianggap aktif.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
