<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurgeChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chart_of_account.delete') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Penghapusan massal tidak bisa dibatalkan; mengetik ulang kata
            // kuncinya adalah satu-satunya rem sebelum puluhan akun hilang.
            'konfirmasi' => ['required', 'string', 'in:HAPUS'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:chart_of_accounts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'konfirmasi.required' => 'Ketik HAPUS pada kotak konfirmasi untuk melanjutkan.',
            'konfirmasi.in' => 'Ketik HAPUS (huruf besar semua) pada kotak konfirmasi untuk melanjutkan.',
            'ids.required' => 'Pilih dulu akun yang mau dihapus.',
        ];
    }
}
