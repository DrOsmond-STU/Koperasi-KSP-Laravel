<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanProductParametersRequest extends FormRequest
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
            'min_plafon' => ['required', 'numeric', 'min:0'],
            'max_plafon' => ['required', 'numeric', 'gte:min_plafon'],
            'provision_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penalty_percentage_per_day' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
