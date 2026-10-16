<?php

namespace App\Http\Requests;

use App\Services\Settings\BrandingService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            // SVG sengaja TIDAK diterima untuk latar: berbeda dari logo yang
            // dipasang di <img>, latar dirender lewat CSS url() dan SVG bisa
            // membawa <script>/<foreignObject> — batasi ke format raster.
            'login_background' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'login_background_size' => ['nullable', Rule::in(array_keys(BrandingService::SIZE_MODES))],
            'login_background_scale' => ['nullable', 'integer', 'min:'.BrandingService::MIN_SCALE, 'max:'.BrandingService::MAX_SCALE],
            'login_background_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'remove_login_background' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login_background.image' => 'Berkas latar harus berupa gambar.',
            'login_background.mimes' => 'Format latar yang didukung: PNG, JPG, atau WEBP.',
            'login_background.max' => 'Ukuran berkas latar maksimal 4 MB.',
            'login_background_scale.min' => 'Skala minimal :min persen.',
            'login_background_scale.max' => 'Skala maksimal :max persen.',
            'login_background_opacity.min' => 'Transparansi berada di rentang 0–100 persen.',
            'login_background_opacity.max' => 'Transparansi berada di rentang 0–100 persen.',
        ];
    }
}
