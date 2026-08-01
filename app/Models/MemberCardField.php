<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCardField extends Model
{
    public const FIELD_KEYS = [
        'photo',
        'logo',
        'name',
        'member_number',
        'branch_name',
        'member_type_name',
        'joined_at',
        'address',
        'custom_text',
    ];

    protected $fillable = [
        'member_card_template_id',
        'field_key',
        'custom_text_value',
        'position_x_mm',
        'position_y_mm',
        'width_mm',
        'height_mm',
        'font_size_pt',
        'font_weight',
        'text_align',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'position_x_mm' => 'decimal:2',
            'position_y_mm' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MemberCardTemplate::class, 'member_card_template_id');
    }

    public function isPhoto(): bool
    {
        return $this->field_key === 'photo';
    }

    public function isLogo(): bool
    {
        return $this->field_key === 'logo';
    }

    /**
     * Resolves this field's display text for a given member. Not used for
     * the "photo" field — MemberCardRenderer reads $member->photo_path
     * directly for that one.
     */
    public function resolveValue(Member $member): string
    {
        return match ($this->field_key) {
            'name' => $member->name,
            'member_number' => $member->member_number,
            'branch_name' => $member->branch?->name ?? '',
            'member_type_name' => $member->memberType?->name ?? '',
            'joined_at' => $member->joined_at?->translatedFormat('d M Y') ?? '',
            'address' => $member->address ?? '',
            'custom_text' => $this->custom_text_value ?? '',
            default => '',
        };
    }
}
