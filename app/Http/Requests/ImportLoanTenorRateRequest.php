<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportLoanTenorRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pinjaman.approve') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ];
    }
}
