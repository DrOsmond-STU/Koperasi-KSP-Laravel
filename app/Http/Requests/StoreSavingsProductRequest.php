<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavingsProductRequest extends FormRequest
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
     * PRD §7: master data dinamis wajib dipetakan ke akun COA yang postable
     * sebelum bisa dipakai transaksi — ditegakkan di sini (AUTH-14), bukan
     * baru gagal saat transaksi berjalan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postableAccount = fn () => Rule::exists('chart_of_accounts', 'id')->where('is_postable', true);

        return [
            'code' => ['required', 'string', 'max:30', 'unique:savings_products,code'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['pokok', 'wajib', 'sukarela', 'berjangka'])],
            'interest_method' => ['required', Rule::in(['flat', 'saldo_harian', 'saldo_rata_rata_bulanan', 'tiered'])],
            'minimum_initial_deposit' => ['nullable', 'numeric', 'min:0'],
            'minimum_subsequent_deposit' => ['nullable', 'numeric', 'min:0'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'admin_fee_period' => ['nullable', Rule::in(['bulanan', 'tahunan']), 'required_with:admin_fee'],
            'withdrawal_penalty_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coa_liability_account_id' => ['required', $postableAccount()],
            'coa_interest_expense_account_id' => ['required', $postableAccount()],
            'initial_rate_percentage' => ['required', 'numeric', 'min:0'],
            'initial_rate_effective_from' => ['nullable', 'date'],
        ];
    }
}
