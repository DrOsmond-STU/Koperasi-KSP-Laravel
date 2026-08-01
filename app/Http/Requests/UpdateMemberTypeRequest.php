<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('member_types', 'code')->ignore($this->route('memberType'))],
            'name' => ['required', 'string', 'max:150'],
            'has_voting_rights' => ['nullable', 'boolean'],
            'requires_mandatory_savings' => ['nullable', 'boolean'],
            'mandatory_savings_default_amount' => ['nullable', 'numeric', 'min:0'],
            'counts_toward_shu' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
