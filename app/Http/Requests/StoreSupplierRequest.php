<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
     * PRD §13.2: Master Supplier wajib dipetakan ke akun COA Hutang Usaha
     * yang postable sebelum bisa dipakai transaksi (AUTH-14).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postableAccount = fn () => Rule::exists('chart_of_accounts', 'id')->where('is_postable', true);

        return [
            'code' => ['required', 'string', 'max:30', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_term' => ['required', Rule::in(['tunai', 'kredit'])],
            'payment_term_days' => ['nullable', 'integer', 'min:1', 'required_if:payment_term,kredit'],
            'coa_payable_account_id' => ['required', $postableAccount()],
        ];
    }
}
