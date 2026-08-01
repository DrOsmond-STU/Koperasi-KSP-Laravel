<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrintSettingsRequest extends FormRequest
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
            'paper_size' => ['required', Rule::in(['a4', 'letter', 'f4'])],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'margin_mm' => ['required', 'integer', 'min:5', 'max:40'],
            'font_size_pt' => ['required', 'integer', 'min:8', 'max:16'],
            'show_address' => ['nullable', 'boolean'],
            'address_text' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
