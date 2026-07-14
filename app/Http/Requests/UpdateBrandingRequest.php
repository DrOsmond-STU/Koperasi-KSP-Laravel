<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('branding.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * SECURITY.md §Web hardening: whitelist tipe file, batas ukuran, validasi
     * MIME nyata (bukan hanya ekstensi) — Laravel `image`/`mimes` rules
     * memvalidasi konten file, bukan hanya nama.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ];
    }
}
