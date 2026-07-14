<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pos.create') ?? false;
    }

    /**
     * The create form renders a fixed number of item rows (no JS framework
     * used yet) — blank rows are dropped before validation.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn (array $row) => filled($row['product_id'] ?? null) && filled($row['qty'] ?? null))
            ->values()
            ->all();

        $this->merge(['items' => $items]);
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
            'payment_method' => ['required', Rule::in(['tunai', 'potong_simpanan'])],
            'savings_account_id' => [
                'required_if:payment_method,potong_simpanan',
                Rule::exists('savings_accounts', 'id')->where('status', 'aktif'),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.qty' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
