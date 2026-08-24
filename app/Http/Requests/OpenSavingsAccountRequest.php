<?php

namespace App\Http\Requests;

use App\Models\SavingsProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpenSavingsAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('simpanan.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('savings_products', 'id')->where('is_active', true)],
            'initial_deposits' => ['array'],
            'initial_deposits.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Setoran awal tiap produk yang dipilih tidak boleh di bawah
     * minimum_initial_deposit produk itu — aturan bisnis per produk,
     * tidak bisa dinyatakan lewat Rule statis di atas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $productIds = (array) $this->input('product_ids', []);
            $deposits = (array) $this->input('initial_deposits', []);

            $products = SavingsProduct::query()->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($productIds as $productId) {
                $product = $products->get($productId);
                if (! $product) {
                    continue;
                }

                $deposit = (float) ($deposits[$productId] ?? 0);
                $minimum = (float) $product->minimum_initial_deposit;

                if ($deposit < $minimum) {
                    $validator->errors()->add(
                        "initial_deposits.{$productId}",
                        "Setoran awal {$product->name} minimal Rp ".number_format($minimum, 0, ',', '.').'.'
                    );
                }
            }
        });
    }
}
