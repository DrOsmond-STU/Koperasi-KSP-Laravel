<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSignatory extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'title',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(DocumentSignatureSlot::class);
    }
}
