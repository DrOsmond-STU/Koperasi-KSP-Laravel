<?php

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Role yang WAJIB MFA menurut SECURITY.md §Authentication — kalau salah
     * satu di antaranya dipilih, checkbox "Wajibkan MFA" tidak boleh false
     * (JS di form sudah mengunci-nya, ini pertahanan kedua di server).
     */
    private const MFA_REQUIRED_ROLES = [
        'teller', 'petugas_kredit', 'petugas_upf', 'bendahara',
        'manajer', 'pengawas', 'admin_sistem',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('user.manage') ?? false;
    }

    /**
     * Normalisasi checkbox HTML — kalau tidak dicentang, `enable_mfa`
     * tidak akan ada di payload sama sekali, jadi diisi false eksplisit
     * agar `boolean` rule tetap lolos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'enable_mfa' => $this->boolean('enable_mfa'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'enable_mfa' => ['required', 'boolean'],
            'member_id' => ['nullable', Rule::exists('members', 'id')->whereNull('user_id')],
            'scope_type' => ['required', Rule::in(['all', 'single', 'multiple'])],
            'single_branch_id' => ['required_if:scope_type,single', 'nullable', Rule::exists('branches', 'id')],
            'branch_ids' => ['required_if:scope_type,multiple', 'nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roles = (array) $this->validated('roles', []);
            $needsMfa = ! empty(array_intersect($roles, self::MFA_REQUIRED_ROLES));

            if ($needsMfa && ! $this->boolean('enable_mfa')) {
                $validator->errors()->add(
                    'enable_mfa',
                    'MFA wajib diaktifkan untuk role internal (teller, petugas kredit, bendahara, manajer, pengawas, admin sistem).',
                );
            }
        });
    }
}
