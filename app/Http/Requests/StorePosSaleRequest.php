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
     *
     * The touchscreen cart also always submits the potong_simpanan/hutang
     * hidden fields (savings_account_id, member_id, loan_product_id,
     * tenor_days) regardless of which payment method tab is active —
     * they're only CSS-hidden, not removed from the DOM — so their value is
     * an empty string rather than genuinely absent whenever the other
     * payment method is chosen. Normalize those to null so the `nullable`
     * rule below skips exists/integer/min checks on them instead of failing
     * on an empty string.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn (array $row) => filled($row['product_id'] ?? null) && filled($row['qty'] ?? null))
            ->values()
            ->all();

        $blankToNull = fn (string $field) => filled($this->input($field)) ? $this->input($field) : null;

        $this->merge([
            'items' => $items,
            'savings_account_id' => $blankToNull('savings_account_id'),
            'member_id' => $blankToNull('member_id'),
            'loan_product_id' => $blankToNull('loan_product_id'),
            'tenor_days' => $blankToNull('tenor_days'),
        ]);
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
            'payment_method' => ['required', Rule::in(['tunai', 'potong_simpanan', 'hutang'])],
            'savings_account_id' => [
                'nullable',
                'required_if:payment_method,potong_simpanan',
                Rule::exists('savings_accounts', 'id')->where('status', 'aktif'),
            ],
            'member_id' => [
                'nullable',
                'required_if:payment_method,hutang',
                Rule::exists('members', 'id')->where('status', 'aktif'),
            ],
            'loan_product_id' => [
                'nullable',
                'required_if:payment_method,hutang',
                Rule::exists('loan_products', 'id')->where('is_active', true),
            ],
            'tenor_days' => ['nullable', 'required_if:payment_method,hutang', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.qty' => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}
