<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Memindahkan kegiatan pinjaman ke satu cabang.
 *
 * Menuntut `master_data.update` — sama seperti menyunting bagan akun atau
 * cabang — karena ini pemeliharaan data induk, bukan transaksi harian.
 * Petugas kredit dan teller sengaja tidak bisa menjalankannya.
 *
 * `konfirmasi` bukan basa-basi: layarnya menampilkan jumlah baris yang akan
 * pindah, dan kotak centang ini memaksa pengurus melewati angka itu dulu
 * sebelum tombolnya bisa ditekan.
 */
class ApplyLoanBranchRepairRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
            'konfirmasi' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Pilih cabang tujuan lebih dulu.',
            'branch_id.exists' => 'Cabang tujuan tidak ditemukan atau sudah nonaktif.',
            'konfirmasi.accepted' => 'Centang kotak konfirmasi untuk menjalankan pemindahan.',
        ];
    }
}
