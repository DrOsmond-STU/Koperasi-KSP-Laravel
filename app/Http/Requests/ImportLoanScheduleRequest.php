<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportLoanScheduleRequest extends FormRequest
{
    /**
     * Dijaga `saldo_awal.create` (bendahara), bukan `pinjaman.update`: alat ini
     * hanya ada untuk menambal jadwal yang luput saat migrasi, dan pemegang
     * migrasi adalah bendahara. Menggantungkannya ke wewenang pinjaman harian
     * akan menjadikan penulisan jadwal massal sebagai kebiasaan operasional,
     * padahal jadwal seharusnya lahir dari pencairan atau penguncian saldo awal.
     */
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'mode' => ['required', Rule::in(['all_or_nothing', 'partial'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih dulu berkas CSV jadwal angsurannya.',
            'file.mimes' => 'Berkas harus CSV.',
            'file.max' => 'Berkas maksimal 5 MB.',
        ];
    }
}
