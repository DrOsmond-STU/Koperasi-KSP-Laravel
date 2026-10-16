<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLoanApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * `pinjaman.approve` is a distinct permission from `pinjaman.create`
     * (segregation of duties — 02_SECURITY.md §Authorization).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pinjaman.approve') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `disbursed_on` adalah tanggal persetujuan sekaligus tanggal uang
     * benar-benar keluar — wajib diisi saat menyetujui, tidak boleh
     * disimpulkan dari hari ini. Koperasi memasukkan pinjaman lama secara
     * susulan (banyak akad Agustus baru dicatat sekarang), jadi
     * menganggap hari pencatatan sebagai hari pencairan membuat kas keluar
     * dan seluruh jadwal angsurannya meleset berbulan-bulan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['setuju', 'tolak'])],
            'notes' => ['nullable', 'string', 'max:255', 'required_if:decision,tolak'],
            'disbursed_on' => [
                'required_if:decision,setuju',
                'nullable',
                'date',
                'before_or_equal:today',
                function ($attribute, $value, $fail) {
                    $loan = $this->route('loan');
                    $diajukan = $loan?->submitted_at;

                    if ($value !== null && $diajukan !== null && $value < $diajukan->toDateString()) {
                        $fail('Tanggal pencairan tidak boleh mendahului tanggal pengajuan ('.$diajukan->translatedFormat('d M Y').').');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return ['disbursed_on' => 'Tanggal pencairan'];
    }

    public function messages(): array
    {
        return [
            'disbursed_on.required_if' => 'Tanggal pencairan wajib diisi saat menyetujui pengajuan.',
            'disbursed_on.before_or_equal' => 'Tanggal pencairan tidak boleh di masa depan.',
        ];
    }
}
