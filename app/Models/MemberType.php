<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberType extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'has_voting_rights',
        'requires_mandatory_savings',
        'mandatory_savings_default_amount',
        'counts_toward_shu',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_voting_rights' => 'boolean',
            'requires_mandatory_savings' => 'boolean',
            'mandatory_savings_default_amount' => 'decimal:2',
            'counts_toward_shu' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
