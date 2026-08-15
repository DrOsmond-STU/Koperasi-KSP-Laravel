<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportLoanRepaymentHistoryRequest extends FormRequest
{
    /** Sama seperti importir jadwal: alat migrasi, wewenangnya di bendahara. */
    public function authorize(): bool
    {
        return $this->user()?->can('saldo_awal.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Berkas riwayat jauh lebih besar dari import lain (20.874 baris,
            // ~2,1 MB), jadi batasnya dinaikkan ke 10 MB.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'mode' => ['required', Rule::in(['all_or_nothing', 'partial'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih dulu berkas CSV riwayat pembayarannya.',
            'file.mimes' => 'Berkas harus CSV.',
            'file.max' => 'Berkas maksimal 10 MB.',
        ];
    }
}
