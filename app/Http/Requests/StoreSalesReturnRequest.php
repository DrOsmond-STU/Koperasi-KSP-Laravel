<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pos.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `pos_sale_item_id` is required — retur penjualan wajib mereferensikan
     * transaksi POS asal (PRD §13.5).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pos_sale_item_id' => ['required', Rule::exists('pos_sale_items', 'id')],
            'qty' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
