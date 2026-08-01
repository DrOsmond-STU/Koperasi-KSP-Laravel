<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:member_types,code'],
            'name' => ['required', 'string', 'max:150'],
            'has_voting_rights' => ['nullable', 'boolean'],
            'requires_mandatory_savings' => ['nullable', 'boolean'],
            'mandatory_savings_default_amount' => ['nullable', 'numeric', 'min:0'],
            'counts_toward_shu' => ['nullable', 'boolean'],
        ];
    }
}
