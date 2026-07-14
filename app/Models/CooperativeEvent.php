<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role;

class CooperativeEvent extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start_at',
        'end_at',
        'location',
        'branch_scope_type',
        'single_branch_id',
        'participant_type',
        'reminder_days',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'reminder_days' => 'array',
        ];
    }

    public function singleBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'single_branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'cooperative_event_branch');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'cooperative_event_members');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'cooperative_event_roles');
    }

    /**
     * Mirrors UserBranchScope::allowedBranchIds() — null means "all branches".
     */
    public function branchIds(): ?array
    {
        return match ($this->branch_scope_type) {
            'all' => null,
            'single' => array_filter([$this->single_branch_id]),
            'multiple' => $this->branches()->pluck('branches.id')->all(),
            default => [],
        };
    }

    public function isCancelled(): bool
    {
        return $this->status === 'dibatalkan';
    }
}
