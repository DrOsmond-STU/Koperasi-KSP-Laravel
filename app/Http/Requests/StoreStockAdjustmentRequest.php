<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('persediaan_koreksi.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'physical_qty' => ['required', 'numeric', 'min:0'],
            'stock_reason_id' => ['required', Rule::exists('stock_reasons', 'id')->where('is_active', true)],
        ];
    }
}
