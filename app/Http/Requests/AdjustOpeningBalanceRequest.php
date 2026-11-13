<?php

namespace App\Http\Requests;

use App\Models\OpeningBalanceBatch;
use App\Services\OpeningBalance\OpeningBalanceAdjustmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ubah / Koreksi Saldo Awal Neraca. Batch draft cukup izin
 * `saldo_awal.update`; batch yang sudah dikunci butuh `saldo_awal.lock`
 * (otoritas yang sama dengan yang mengunci) + konfirmasi kata sandi.
 */
class AdjustOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OpeningBalanceBatch $batch */
        $batch = $this->route('batch');

        return $this->user()?->can($batch->isDraft() ? 'saldo_awal.update' : 'saldo_awal.lock') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OpeningBalanceBatch $batch */
        $batch = $this->route('batch');
        $locked = ! $batch->isDraft();

        $neracaAccount = Rule::exists('chart_of_accounts', 'id')
            ->where('statement', 'NERACA')
            ->where('is_postable', true)
            ->whereNotIn('code', OpeningBalanceAdjustmentService::EXCLUDED_ACCOUNT_CODES);

        return [
            'balances' => ['required', 'array'],
            'balances.*' => ['nullable', 'numeric', 'between:-999999999999999.99,999999999999999.99'],
            'balancing_account_id' => ['nullable', 'integer', $neracaAccount],
            'reason' => [$locked ? 'required' : 'nullable', 'string', 'max:500'],
            'entry_date' => ['nullable', 'date', 'after_or_equal:'.$batch->cutoff_date->toDateString()],
            'password' => [$locked ? 'required' : 'nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'balances' => 'saldo baru',
            'balancing_account_id' => 'akun penyeimbang',
            'reason' => 'alasan koreksi',
            'entry_date' => 'tanggal jurnal',
            'password' => 'kata sandi',
        ];
    }
}
