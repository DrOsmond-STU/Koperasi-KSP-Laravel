<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRetributionTypeRequest extends FormRequest
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
     * `is_active` tidak boleh true tanpa `coa_revenue_account_id` — kalau
     * lolos, RetributionService akan menolak semua transaksi retribusi
     * (tidak bisa menjurnal ke akun yang belum ada).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('retribution_types', 'code')->ignore($this->route('retributionType'))],
            'name' => ['required', 'string', 'max:150'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'coa_revenue_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('is_postable', true)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => [
                'nullable',
                'boolean',
                function ($attribute, $value, $fail) {
                    if ($value && ! $this->input('coa_revenue_account_id')) {
                        $fail('Jenis retribusi tidak bisa diaktifkan sebelum akun COA di-link.');
                    }
                },
            ],
        ];
    }
}
