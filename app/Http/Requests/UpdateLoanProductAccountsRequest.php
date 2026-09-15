<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pemetaan akun jurnal produk pinjaman hanya bisa diisi saat produk dibuat,
 * padahal koperasi yang mengganti bagan akunnya setelah itu perlu
 * mengarahkan ulang produk lama — termasuk akun kas pencairan, yang kalau
 * salah membuat setiap persetujuan pinjaman gagal posting.
 */
class UpdateLoanProductAccountsRequest extends FormRequest
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

        return [
            'coa_receivable_account_id' => ['required', $postableAccount()],
            'coa_interest_income_account_id' => ['required', $postableAccount()],
            'coa_provision_income_account_id' => ['required', $postableAccount()],
            'coa_penalty_receivable_account_id' => ['required', $postableAccount()],
            // Boleh kosong: yang kosong tetap memakai akun kas bawaan (1101).
            'coa_cash_account_id' => ['nullable', $postableAccount()],
        ];
    }
}
