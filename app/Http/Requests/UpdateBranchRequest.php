<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master_data.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('branches', 'code')->ignore($branch)],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'parent_branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
                // Ditegakkan di server, bukan sekadar disembunyikan dari
                // dropdown: rantai induk yang melingkar akan membuat
                // penelusuran hierarki cabang berputar tanpa henti.
                function (string $attribute, mixed $value, Closure $fail) use ($branch) {
                    if ($value === null || ! $branch instanceof Branch) {
                        return;
                    }

                    if ((int) $value === $branch->id) {
                        $fail('Cabang tidak bisa menjadi induk dirinya sendiri.');

                        return;
                    }

                    $ancestor = Branch::query()->find($value)?->parentBranch;
                    $guard = 0;

                    while ($ancestor !== null && $guard++ < 50) {
                        if ($ancestor->id === $branch->id) {
                            $fail('Cabang induk tidak boleh diisi dengan cabang yang berada di bawahnya.');

                            return;
                        }

                        $ancestor = $ancestor->parentBranch;
                    }
                },
            ],
            'operational_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode cabang',
            'name' => 'nama cabang',
            'address' => 'alamat',
            'parent_branch_id' => 'cabang induk',
            'operational_date' => 'tanggal operasional',
        ];
    }
}
