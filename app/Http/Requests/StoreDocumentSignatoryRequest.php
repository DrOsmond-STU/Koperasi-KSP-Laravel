<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentSignatoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:100'],
        ];
    }
}
