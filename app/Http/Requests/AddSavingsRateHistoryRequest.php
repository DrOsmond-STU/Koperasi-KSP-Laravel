<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddSavingsRateHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tarif.update') ?? false;
    }

    /**
     * `effective_from` must be today or later — this is what makes rate
     * changes non-retroactive (E2E-05): a new row can never alter which
     * rate resolves for a past date, only for the new date onward.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate_percentage' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
