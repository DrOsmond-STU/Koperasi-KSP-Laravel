<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on models using this scope to the branches the
 * currently authenticated user is allowed to see (PRD §6, ARCHITECTURE §6).
 *
 * - scope_type "all"      -> no restriction (allowedBranchIds() returns null)
 * - scope_type "single"   -> exactly one branch_id
 * - scope_type "multiple" -> whereIn a list of branch_id
 *
 * This is a defense-in-depth layer; the EnsureBranchScope middleware also
 * validates branch_id on write requests before they reach the model layer.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $allowedBranchIds = $user->allowedBranchIds();

        // null = "all branch" scope, unrestricted.
        if ($allowedBranchIds === null) {
            return;
        }

        $builder->whereIn($model->qualifyColumn('branch_id'), $allowedBranchIds);
    }
}
