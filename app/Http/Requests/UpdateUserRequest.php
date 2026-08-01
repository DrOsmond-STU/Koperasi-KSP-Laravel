<?php

namespace App\Http\Requests;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'member_id' => ['nullable', Rule::exists('members', 'id')->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->route('user')?->id))],
            'scope_type' => ['required', Rule::in(['all', 'single', 'multiple'])],
            'single_branch_id' => ['required_if:scope_type,single', 'nullable', Rule::exists('branches', 'id')],
            'branch_ids' => ['required_if:scope_type,multiple', 'nullable', 'array'],
            'branch_ids.*' => ['integer', Rule::exists('branches', 'id')],
        ];
    }
}
