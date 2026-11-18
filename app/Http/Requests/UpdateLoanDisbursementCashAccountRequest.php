<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Akun kas sumber pencairan pinjaman. Wajib akun yang bisa diposting —
 * akun header ditolak JournalEngine, dan kalau lolos sampai tersimpan di
 * sini kegagalannya baru muncul saat seseorang menyetujui pinjaman.
 */
class UpdateLoanDisbursementCashAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loan_disbursement_account_id' => [
                'nullable', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where('is_postable', true),
            ],
        ];
    }
}
