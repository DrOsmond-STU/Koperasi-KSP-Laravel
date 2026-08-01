<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postableAccount = fn () => Rule::exists('chart_of_accounts', 'id')->where('is_postable', true);

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('products', 'code')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'coa_inventory_account_id' => ['required', $postableAccount()],
            'coa_cogs_account_id' => ['required', $postableAccount()],
            'coa_sales_revenue_account_id' => ['required', $postableAccount()],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }
}
