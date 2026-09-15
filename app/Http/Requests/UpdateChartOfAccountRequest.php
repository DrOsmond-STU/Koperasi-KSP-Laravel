<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
     * `is_postable` is locked on for those same codes: core services post
     * to them directly, so demoting one to a header account makes
     * JournalEngine reject every posting that touches it — loan
     * disbursement, savings, teller cash, POS, retribution — with nothing
     * at the DB layer to catch it.
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

    /**
     * Checked here rather than as a rule on `is_postable` so it also fires
     * when the checkbox is absent from the payload (unchecked boxes submit
     * nothing), matching what the controller actually persists via
     * `$request->boolean('is_postable')`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ChartOfAccount $account */
            $account = $this->route('chartOfAccount');

            if ($account->isProtected() && ! $this->boolean('is_postable')) {
                $validator->errors()->add(
                    'is_postable',
                    "Akun \"{$account->code}\" dipakai inti sistem sebagai tujuan jurnal dan harus tetap bisa diposting — jadikan akun header akan menggagalkan pencairan pinjaman, setoran/penarikan simpanan, kas teller, POS, dan retribusi."
                );
            }
        });
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
