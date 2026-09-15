<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chart_of_account.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'mode' => ['required', Rule::in(['all_or_nothing', 'partial'])],
        ];
    }
}
