<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRetributionTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('retribusi_upf.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'payer_type' => ['required', Rule::in(['umum', 'anggota'])],
            'payer_name' => ['required_if:payer_type,umum', 'nullable', 'string', 'max:150'],
            'member_id' => ['required_if:payer_type,anggota', 'nullable', Rule::exists('members', 'id')],
            'retribution_type_id' => [
                'required_if:payer_type,umum',
                'nullable',
                Rule::exists('retribution_types', 'id')->where('is_active', true),
            ],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['tunai', 'transfer'])],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'retribution_type_id' => 'Jenis Retribusi',
            'member_id' => 'Anggota (Kios/Blok)',
            'payer_name' => 'Nama Pembayar',
        ];
    }
}
