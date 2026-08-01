<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSignatureSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cetakan.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_group' => ['required', Rule::in([
                'pengajuan_pinjaman', 'pengajuan_penarikan', 'kas_keluar', 'kas_masuk',
                'jurnal_umum', 'dokumen_gudang', 'aktiva_tetap', 'laporan_kas_bank',
                'laporan_upf', 'unit_usaha',
            ])],
            'label' => ['required', 'string', 'max:100'],
            'document_signatory_id' => ['nullable', Rule::exists('document_signatories', 'id')],
        ];
    }
}
