<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Beda dari CancelLoanDisbursementRequest ('pinjaman.delete', membatalkan
 * PENCAIRAN pinjaman — manajer saja): ini membatalkan SATU pembayaran
 * angsuran yang salah catat, gerbang izinnya 'angsuran.delete' — diberikan
 * ke role yang juga mencatat angsuran (Teller, Petugas Kredit, Manajer),
 * sama pola dengan CancelSavingsTransactionRequest ('simpanan.delete').
 * Siapa persisnya yang boleh membatalkan BARIS tertentu (pembuatnya
 * sendiri, atau admin_sistem/manajer) dicek terpisah di controller lewat
 * LoanRepayment::canBeCancelledBy() (AuthorizesOwner) — permission ini
 * cuma gerbang MODUL-nya.
 */
class CancelLoanRepaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('angsuran.delete') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
