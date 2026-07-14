<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pembelian.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `purchase_item_id` is required (06_TESTING.md STK-03: retur wajib
     * mereferensikan transaksi pembelian asal).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_item_id' => ['required', Rule::exists('purchase_items', 'id')],
            'stock_reason_id' => ['required', Rule::exists('stock_reasons', 'id')->where('is_active', true)],
            'qty' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
