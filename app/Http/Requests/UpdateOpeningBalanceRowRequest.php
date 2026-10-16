<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah satu baris Saldo Awal.
 *
 * Aturannya sengaja TIDAK ditulis ulang, melainkan diambil dari
 * Store*RowRequest yang sudah ada, supaya validasi lewat form tambah dan
 * lewat form ubah dijamin identik selamanya. StoreOpeningBalanceInstallment
 * RowRequest membaca $this->route('batch'), jadi route resolver-nya ikut
 * dioper — kalau tidak, batasan "pinjaman harus dari batch yang sama"
 * (OB-09) akan diam-diam berhenti bekerja.
 */
class UpdateOpeningBalanceRowRequest extends FormRequest
{
    private const RULE_SOURCE = [
        'savings' => StoreOpeningBalanceSavingRowRequest::class,
        'loans' => StoreOpeningBalanceLoanRowRequest::class,
        'installments' => StoreOpeningBalanceInstallmentRowRequest::class,
        'coa' => StoreOpeningBalanceCoaRowRequest::class,
        'upf' => StoreOpeningBalanceUpfRowRequest::class,
        'stock' => StoreOpeningBalanceStockRowRequest::class,
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('saldo_awal.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subModule = (string) $this->route('subModule');

        if (! isset(self::RULE_SOURCE[$subModule])) {
            abort(404);
        }

        $source = new (self::RULE_SOURCE[$subModule])();
        $source->setRouteResolver($this->getRouteResolver());

        return $source->rules();
    }
}
