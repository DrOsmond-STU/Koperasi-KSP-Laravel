<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit transaksi Setor/Tarik dari halaman Riwayat — gerbangnya
 * 'simpanan.delete' (bukan 'simpanan.create') karena secara mekanisme ini
 * MEMBATALKAN baris asli dulu (SavingsService::editTransaction() ->
 * reverseTransaction()) sebelum mencatat baris baru, sama pola izin dengan
 * CancelSavingsTransactionRequest. Siapa persisnya yang boleh mengedit
 * BARIS tertentu (pembuatnya sendiri, atau admin_sistem/manajer) dicek
 * terpisah di controller lewat SavingsTransaction::canBeCancelledBy().
 */
class EditSavingsTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('simpanan.delete') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['setor', 'tarik'])],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
