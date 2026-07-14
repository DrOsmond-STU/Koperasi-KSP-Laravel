<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per DepreciationEngine::run() invocation (AUD-06) — the
 * Auditable trait records its creation (run id, period, counts,
 * triggered_by) in audit_logs automatically.
 */
class DepreciationBatchRun extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'period',
        'assets_processed',
        'assets_skipped',
        'triggered_by',
    ];

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
