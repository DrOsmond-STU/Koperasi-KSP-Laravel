<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideFixedAssetApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * `aktiva_tetap.approve` is a distinct permission from
     * `aktiva_tetap.create` (segregation of duties).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('aktiva_tetap.approve') ?? false;
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
        ];
    }
}
