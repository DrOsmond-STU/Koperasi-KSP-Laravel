<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton (always id=1) — read/write it through CashSettingsService, which
 * owns the cache and the "always exactly one row" invariant. Mirrors
 * PrintSetting / AppBrandingSetting's pattern exactly.
 */
class CashSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'id',
        'loan_disbursement_account_id',
        'loan_branch_id',
        'updated_by',
    ];

    public function loanDisbursementAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'loan_disbursement_account_id');
    }

    public function loanBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'loan_branch_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
