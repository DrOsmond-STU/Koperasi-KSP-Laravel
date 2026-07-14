<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedAssetDisposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('aktiva_tetap.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fixed_asset_id' => ['required', Rule::exists('fixed_assets', 'id')->where('status', 'aktif')],
            'disposal_type' => ['required', Rule::in(['dijual', 'dihapusbukukan', 'hilang'])],
            'sale_amount' => ['nullable', 'numeric', 'min:0.01', 'required_if:disposal_type,dijual'],
        ];
    }
}
