<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSavingsProductParametersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tarif.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'minimum_initial_deposit' => ['nullable', 'numeric', 'min:0'],
            'minimum_subsequent_deposit' => ['nullable', 'numeric', 'min:0'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'admin_fee_period' => ['nullable', Rule::in(['bulanan', 'tahunan']), 'required_with:admin_fee'],
            'withdrawal_penalty_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
