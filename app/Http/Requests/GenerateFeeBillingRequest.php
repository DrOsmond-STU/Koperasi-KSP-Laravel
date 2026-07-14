<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFeeBillingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('upf_tagihan.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', Rule::exists('tenants', 'id')],
            'fee_type_id' => ['required', Rule::exists('fee_types', 'id')->where('is_active', true)],
            'period' => ['required', 'date_format:Y-m'],
            'meter_start' => ['nullable', 'numeric', 'min:0'],
            'meter_end' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
