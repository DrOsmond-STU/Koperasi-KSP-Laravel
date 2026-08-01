<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGeneralJournalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('jurnal.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowed = $this->user()?->allowedBranchIds();

        return [
            'entry_date' => ['required', 'date'],
            'branch_id' => array_filter([
                'required',
                Rule::exists('branches', 'id'),
                $allowed !== null ? Rule::in($allowed) : null,
            ]),
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('is_postable', true)],
            'lines.*.position' => ['required', Rule::in(['debit', 'kredit'])],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * Cek Σdebit = Σkredit di level form — pesan ramah untuk user SEBELUM
     * request sampai ke JournalEngine (yang tetap jadi pertahanan kedua,
     * integer-cents-safe, kalau ada celah floating-point di sini).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            $totalDebit = 0;
            $totalKredit = 0;

            foreach ($lines as $line) {
                $amount = (float) ($line['amount'] ?? 0);

                if (($line['position'] ?? null) === 'debit') {
                    $totalDebit += $amount;
                } elseif (($line['position'] ?? null) === 'kredit') {
                    $totalKredit += $amount;
                }
            }

            if (round($totalDebit, 2) !== round($totalKredit, 2)) {
                $validator->errors()->add(
                    'lines',
                    'Total debit (Rp '.number_format($totalDebit, 0, ',', '.').') harus sama dengan total kredit (Rp '.number_format($totalKredit, 0, ',', '.').').',
                );
            }
        });
    }
}
