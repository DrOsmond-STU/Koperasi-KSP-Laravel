<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only (SECURITY.md §Audit log, AUD-04). Rows are written exclusively
 * by AuditableObserver; update()/delete() are disabled at the application
 * layer as defense-in-depth on top of the fact that no controller/route in
 * this app ever calls them on this model.
 */
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_role',
        'branch_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('audit_logs bersifat append-only — tidak boleh diubah.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('audit_logs bersifat append-only — tidak boleh dihapus.');
    }
}
