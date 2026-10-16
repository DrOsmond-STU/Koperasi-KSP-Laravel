<?php

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules;

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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enable_mfa' => $this->boolean('enable_mfa'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `password` is nullable — leave blank on the edit form to keep the
     * current password unchanged.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => array_merge(['nullable'], array_slice($this->passwordRules(), 1)),
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'enable_mfa' => ['required', 'boolean'],
            'member_id' => ['nullable', Rule::exists('members', 'id')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->route('user')?->id))],
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
