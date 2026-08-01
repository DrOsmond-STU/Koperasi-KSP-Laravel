<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('chart_of_account.update') ?? false;
    }

    /**
     * `code` is locked for ChartOfAccount::PROTECTED_CODES (looked up by
     * literal string in core services, not by FK) and `parent_code` is
     * checked for cycles by walking its ancestor chain, since the DB has
     * no self-referencing constraint on `parent_code` to catch either.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ChartOfAccount $account */
        $account = $this->route('chartOfAccount');

        return [
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('chart_of_accounts', 'code')->ignore($account),
                function ($attribute, $value, $fail) use ($account) {
                    if ($account->isProtected() && $value !== $account->code) {
                        $fail("Kode akun \"{$account->code}\" dipakai inti sistem dan tidak bisa diubah.");
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['ASET', 'LIABILITAS', 'EKUITAS', 'PENDAPATAN', 'BEBAN'])],
            'group' => ['nullable', 'string', 'max:255'],
            'normal_balance' => ['required', Rule::in(['DEBIT', 'KREDIT'])],
            'is_postable' => ['nullable', 'boolean'],
            'parent_code' => [
                'nullable', 'string',
                Rule::exists('chart_of_accounts', 'code'),
                Rule::notIn([$account->code]),
                function ($attribute, $value, $fail) use ($account) {
                    if ($value && $this->isDescendant($account, $value)) {
                        $fail('Akun induk tidak boleh salah satu akun anak/turunannya sendiri (siklus).');
                    }
                },
            ],
            'statement' => ['required', Rule::in(['NERACA', 'LABA_RUGI'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function isDescendant(ChartOfAccount $account, string $candidateParentCode): bool
    {
        $code = $candidateParentCode;
        $seen = [];

        while ($code !== null && ! in_array($code, $seen, true)) {
            if ($code === $account->code) {
                return true;
            }

            $seen[] = $code;
            $code = ChartOfAccount::query()->where('code', $code)->value('parent_code');
        }

        return false;
    }
}
