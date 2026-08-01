<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideSavingsWithdrawalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * `simpanan.approve` is distinct from `simpanan.create`/`update` —
     * segregation of duties (02_SECURITY.md §Authorization).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('simpanan.approve') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['setuju', 'tolak'])],
            'notes' => ['nullable', 'string', 'max:255', 'required_if:decision,tolak'],
        ];
    }
}
