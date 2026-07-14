<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserBranchScope extends Model
{
    use Auditable, HasFactory;

    protected $table = 'user_branch_scope';

    protected $fillable = [
        'user_id',
        'scope_type',
        'single_branch_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function singleBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'single_branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'user_branch_scope_branch',
            'user_branch_scope_id',
            'branch_id'
        );
    }

    /**
     * Branch IDs this scope is allowed to access.
     * Returns null when scope is "all" — caller must treat null as unrestricted.
     */
    public function allowedBranchIds(): ?array
    {
        return match ($this->scope_type) {
            'all' => null,
            'single' => array_filter([$this->single_branch_id]),
            'multiple' => $this->branches()->pluck('branches.id')->all(),
            default => [],
        };
    }
}
