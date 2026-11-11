<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Edit Jurnal Umum = balik entri lama + posting entri pengganti.
 * Aturan baris sama persis dengan StoreGeneralJournalRequest supaya
 * validasi balance tidak mungkin berbeda antara buat-baru dan koreksi.
 */
class UpdateGeneralJournalRequest extends StoreGeneralJournalRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('jurnal.adjust') ?? false;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'reason' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi untuk konfirmasi koreksi.',
        ];
    }
}
