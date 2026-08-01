<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceSavingRowRequest extends FormRequest
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
     * Field sama dengan validator CSV Simpanan (OpeningBalanceImportService::validateSavings)
     * — bedanya di sini anggota/produk dipilih dari dropdown (id langsung),
     * bukan diketik sebagai kode.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', Rule::exists('members', 'id')],
            'savings_product_id' => ['required', Rule::exists('savings_products', 'id')],
            'account_number' => ['nullable', 'string', 'max:50'],
            'balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
