<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedAssetCategoryRequest extends FormRequest
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
     * PRD §14.1: kategori aktiva tetap wajib dipetakan ke akun COA
     * Aset/Akumulasi Penyusutan/Beban Penyusutan yang postable (AUTH-14).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postableAccount = fn () => Rule::exists('chart_of_accounts', 'id')->where('is_postable', true);

        return [
            'code' => ['required', 'string', 'max:30', 'unique:fixed_asset_categories,code'],
            'name' => ['required', 'string', 'max:150'],
            'default_depreciation_method' => ['required', Rule::in(['garis_lurus', 'saldo_menurun'])],
            'default_useful_life_months' => ['required', 'integer', 'min:1'],
            'coa_asset_account_id' => ['required', $postableAccount()],
            'coa_accumulated_depreciation_account_id' => ['required', $postableAccount()],
            'coa_depreciation_expense_account_id' => ['required', $postableAccount()],
        ];
    }
}
