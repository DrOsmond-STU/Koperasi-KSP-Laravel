<?php

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('user.manage') ?? false;
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
            'member_id' => ['nullable', Rule::exists('members', 'id')->whereNull('user_id')],
            'scope_type' => ['required', Rule::in(['all', 'single', 'multiple'])],
            'single_branch_id' => ['required_if:scope_type,single', 'nullable', Rule::exists('branches', 'id')],
            'branch_ids' => ['required_if:scope_type,multiple', 'nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
        ];
    }
}
