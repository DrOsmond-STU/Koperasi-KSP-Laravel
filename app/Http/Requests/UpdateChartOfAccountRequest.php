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
     * `is_postable` is locked on for those same codes — see withValidator()
     * below for why it is enforced there and not as a rule here.
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
     * Akun inti dijurnal langsung oleh service, jadi menjadikannya akun
     * header melumpuhkan pencairan pinjaman, setoran/penarikan simpanan,
     * kas teller, POS, dan retribusi sekaligus — persis kejadian 15 Sep
     * 2026, ketika akun kas '1101' dijadikan header dan setiap persetujuan
     * pinjaman balas HTTP 500.
     *
     * Diperiksa di sini, bukan sebagai aturan pada `is_postable`, supaya
     * ikut berjalan ketika kotak centangnya TIDAK ADA di payload sama
     * sekali — checkbox yang tidak dicentang memang tidak mengirim apa pun,
     * dan itulah yang disimpan controller lewat `$request->boolean()`.
     * Aturan biasa akan terlewat begitu saja pada kasus itu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ChartOfAccount $account */
            $account = $this->route('chartOfAccount');

            if ($account->isProtected() && ! $this->boolean('is_postable')) {
                $validator->errors()->add(
                    'is_postable',
                    "Akun \"{$account->code}\" dipakai inti sistem sebagai tujuan jurnal dan harus tetap bisa diposting — menjadikannya akun header akan menggagalkan pencairan pinjaman, setoran/penarikan simpanan, kas teller, POS, dan retribusi.",
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
