<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ubah Produk Simpanan — cerminan StoreSavingsProductRequest, dengan dua
 * perbedaan yang disengaja.
 *
 * Pertama, `code` unik dengan mengabaikan baris ini sendiri, supaya menyimpan
 * ulang tanpa mengubah kode tidak ditolak sebagai duplikat.
 *
 * Kedua, tarif jasa TIDAK ikut diubah di sini. Tarif hidup di
 * savings_product_rate_histories dan dipilih berdasarkan tanggal berlaku
 * (SavingsProduct::rateAt()) — menimpanya lewat form ubah akan mengubah
 * perhitungan transaksi yang sudah lewat, persis yang dicegah PRD §2.4.
 */
class UpdateSavingsProductRequest extends FormRequest
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
        $product = $this->route('savingsProduct');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('savings_products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::exists('savings_product_categories', 'code')->where('is_active', true)],
            'interest_method' => ['required', Rule::in(['flat', 'saldo_harian', 'saldo_rata_rata_bulanan', 'tiered'])],
            'minimum_initial_deposit' => ['nullable', 'numeric', 'min:0'],
            'minimum_subsequent_deposit' => ['nullable', 'numeric', 'min:0'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'admin_fee_period' => ['nullable', Rule::in(['bulanan', 'tahunan']), 'required_with:admin_fee'],
            'withdrawal_penalty_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coa_liability_account_id' => ['required', $postableAccount()],
            'coa_interest_expense_account_id' => ['required', $postableAccount()],
        ];
    }
}
