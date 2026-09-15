<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Beda dari AddLoanRateHistoryRequest: ini MENGOREKSI baris tarif yang
 * sudah tersimpan (mis. migrasi 31 Juli 2026 yang salah ketik jadi Agustus
 * — laporan staf 26 Agu 2026), bukan menambah kebijakan tarif baru ke
 * depan. Karena itu `effective_from` di sini TIDAK dibatasi
 * after_or_equal:today (aturan non-retroaktif E2E-05 itu hanya berlaku
 * untuk menambah baris baru lewat AddLoanRateHistoryRequest — lihat
 * TarifParameterController::addLoanRate() vs updateLoanRate()).
 *
 * @return array<string, ValidationRule|array<mixed>|string>
 */
class UpdateLoanRateHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tarif.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'rate_percentage' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
        ];
    }
}
