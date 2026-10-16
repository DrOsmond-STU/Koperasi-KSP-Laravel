<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StafLoanRepaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pinjaman.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Angsuran Pokok/Jasa/Denda dipisah jadi 3 field sendiri-sendiri
     * (instruksi KPPD Depok, 26 Agu 2026) — bukan satu "amount" yang
     * dihitung otomatis lagi (lihat LoanRepaymentService::
     * recordManualPayment()). "Nominal Bayar" di form cuma tampilan
     * (dijumlahkan lewat JS), bukan field yang disubmit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loan_id' => ['required', 'integer', Rule::exists('loans', 'id')],
            'principal_portion' => ['required', 'numeric', 'min:0'],
            'interest_portion' => ['required', 'numeric', 'min:0'],
            'penalty_portion' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'cash_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $total = (float) $this->input('principal_portion', 0)
                + (float) $this->input('interest_portion', 0)
                + (float) $this->input('penalty_portion', 0);

            if ($total <= 0) {
                $validator->errors()->add('principal_portion', 'Angsuran Pokok, Jasa, dan Denda tidak boleh ketiganya nol.');
            }
        });
    }
}
