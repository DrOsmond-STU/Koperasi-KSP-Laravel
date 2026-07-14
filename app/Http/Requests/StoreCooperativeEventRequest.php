<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativeEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('kalender.create') ?? false;
    }

    /**
     * The create form has no JS in this app yet — reminder days are typed
     * as a plain comma-separated string and parsed into an array here.
     */
    protected function prepareForValidation(): void
    {
        $raw = (string) $this->input('reminder_days_raw', '');

        $days = collect(explode(',', $raw))
            ->map(fn (string $value) => trim($value))
            ->filter(fn (string $value) => $value !== '' && ctype_digit($value))
            ->map(fn (string $value) => (int) $value)
            ->values()
            ->all();

        $this->merge(['reminder_days' => $days]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'location' => ['nullable', 'string', 'max:150'],
            'branch_scope_type' => ['required', Rule::in(['single', 'multiple', 'all'])],
            'single_branch_id' => ['nullable', 'required_if:branch_scope_type,single', Rule::exists('branches', 'id')],
            'branch_ids' => ['nullable', 'array', 'required_if:branch_scope_type,multiple'],
            'branch_ids.*' => [Rule::exists('branches', 'id')],
            'participant_type' => ['required', Rule::in(['anggota_tertentu', 'role_tertentu', 'semua_anggota_cabang'])],
            'member_ids' => ['nullable', 'array', 'required_if:participant_type,anggota_tertentu'],
            'member_ids.*' => [Rule::exists('members', 'id')],
            'role_ids' => ['nullable', 'array', 'required_if:participant_type,role_tertentu'],
            'role_ids.*' => [Rule::exists('roles', 'id')],
            'reminder_days' => ['nullable', 'array'],
            'reminder_days.*' => ['integer', 'min:0'],
        ];
    }
}
