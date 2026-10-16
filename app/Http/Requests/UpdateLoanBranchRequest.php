<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cabang pemilik transaksi pinjaman. Wajib cabang yang aktif — cabang
 * nonaktif tidak boleh jadi tujuan pembukuan baru, dan kalau lolos sampai
 * tersimpan di sini akibatnya baru terasa saat ada yang mengajukan atau
 * mencairkan pinjaman.
 */
class UpdateLoanBranchRequest extends FormRequest
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
            'loan_branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['loan_branch_id' => 'Cabang transaksi pinjaman'];
    }

    public function messages(): array
    {
        return [
            'loan_branch_id.exists' => 'Cabang tersebut tidak ada atau sudah dinonaktifkan.',
        ];
    }
}
