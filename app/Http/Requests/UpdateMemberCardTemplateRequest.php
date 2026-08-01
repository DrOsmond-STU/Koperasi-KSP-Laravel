<?php

namespace App\Http\Requests;

use App\Models\MemberCardField;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberCardTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('member_card.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'width_mm' => ['required', 'numeric', 'min:10', 'max:300'],
            'height_mm' => ['required', 'numeric', 'min:10', 'max:300'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],

            'fields' => ['required', 'array', 'min:1'],
            'fields.*.field_id' => ['nullable', Rule::exists('member_card_fields', 'id')->where('member_card_template_id', $this->route('memberCardTemplate')?->id)],
            'fields.*.field_key' => ['required', Rule::in(MemberCardField::FIELD_KEYS)],
            'fields.*.custom_text_value' => ['nullable', 'string', 'max:255'],
            'fields.*.position_x_mm' => ['required', 'numeric', 'min:0'],
            'fields.*.position_y_mm' => ['required', 'numeric', 'min:0'],
            'fields.*.width_mm' => ['nullable', 'numeric', 'min:0'],
            'fields.*.height_mm' => ['nullable', 'numeric', 'min:0'],
            'fields.*.font_size_pt' => ['nullable', 'integer', 'min:1', 'max:72'],
            'fields.*.font_weight' => ['nullable', Rule::in(['normal', 'bold'])],
            'fields.*.text_align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'fields.*.color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Sama seperti StoreMemberCardTemplateRequest — lihat catatan di sana.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $cardWidth = (float) $this->input('width_mm');
            $cardHeight = (float) $this->input('height_mm');

            foreach ((array) $this->input('fields', []) as $index => $field) {
                $right = (float) ($field['position_x_mm'] ?? 0) + (float) ($field['width_mm'] ?? 0);
                $bottom = (float) ($field['position_y_mm'] ?? 0) + (float) ($field['height_mm'] ?? 0);

                if ($right > $cardWidth) {
                    $validator->errors()->add("fields.{$index}.position_x_mm", 'Posisi + lebar field melewati batas kanan kartu.');
                }

                if ($bottom > $cardHeight) {
                    $validator->errors()->add("fields.{$index}.position_y_mm", 'Posisi + tinggi field melewati batas bawah kartu.');
                }
            }
        });
    }
}
