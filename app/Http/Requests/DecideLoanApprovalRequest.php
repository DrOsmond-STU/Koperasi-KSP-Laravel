<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLoanApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * `pinjaman.approve` is a distinct permission from `pinjaman.create`
     * (segregation of duties — 02_SECURITY.md §Authorization).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pinjaman.approve') ?? false;
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
