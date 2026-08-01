<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberCardTemplate extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'width_mm',
        'height_mm',
        'background_color',
        'background_image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(MemberCardField::class)->orderBy('sort_order');
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->with('fields')->first();
    }

    /**
     * Only one template may be active at a time — activating this one
     * deactivates every other template.
     */
    public function activate(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
