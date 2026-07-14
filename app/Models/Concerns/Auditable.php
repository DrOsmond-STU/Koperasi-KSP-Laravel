<?php

namespace App\Models\Concerns;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Apply to any model whose create/update/delete must be tracked in
 * audit_logs (SECURITY.md §Audit log — jurnal, master data dinamis,
 * mapping COA, saldo awal, RBAC, dst). Do not apply to AuditLog itself.
 *
 * Registers event closures directly (static::created()/updated()/deleted())
 * rather than static::observe(), which internally does `new static` and
 * re-enters this same model's boot cycle — Eloquent's booting guard rejects
 * that re-entrant construction.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            app(AuditableObserver::class)->created($model);
        });

        static::updated(function (Model $model): void {
            app(AuditableObserver::class)->updated($model);
        });

        static::deleted(function (Model $model): void {
            app(AuditableObserver::class)->deleted($model);
        });
    }
}
