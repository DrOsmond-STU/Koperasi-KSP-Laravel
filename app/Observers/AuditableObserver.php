<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer registered by the Auditable trait — records
 * create/update/delete on any model that opts in, so individual
 * controllers/services never have to remember to log manually
 * (ARCHITECTURE §6, SECURITY §Audit log, AUD-01/02/03).
 */
class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'create', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $before = array_intersect_key($model->getOriginal(), $model->getChanges());
        $this->record($model, 'update', $before, $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'delete', $model->getAttributes(), null);
    }

    private function record(Model $model, string $action, ?array $before, ?array $after): void
    {
        $user = auth()->user();

        AuditLog::query()->create([
            'actor_id' => $user?->id,
            'actor_role' => $user?->getRoleNames()->implode(','),
            'branch_id' => $model->getAttributes()['branch_id'] ?? null,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
