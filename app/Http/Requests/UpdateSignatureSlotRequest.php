<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSignatureSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cetakan.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'document_signatory_id' => ['nullable', Rule::exists('document_signatories', 'id')],
        ];
    }
}
