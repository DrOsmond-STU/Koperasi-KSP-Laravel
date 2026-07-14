<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'parent_branch_id',
        'operational_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'operational_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function parentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'parent_branch_id');
    }

    public function childBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'parent_branch_id');
    }
}
