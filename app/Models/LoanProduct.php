<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class LoanProduct extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'min_plafon',
        'max_plafon',
        'min_tenor_days',
        'max_tenor_days',
        'calculation_method',
        'provision_fee_percentage',
        'penalty_percentage_per_day',
        'approval_threshold',
        'coa_receivable_account_id',
        'coa_interest_income_account_id',
        'coa_provision_income_account_id',
        'coa_penalty_receivable_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_plafon' => 'decimal:2',
            'max_plafon' => 'decimal:2',
            'provision_fee_percentage' => 'decimal:2',
            'penalty_percentage_per_day' => 'decimal:3',
            'approval_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_receivable_account_id');
    }

    public function interestIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_interest_income_account_id');
    }

    public function provisionIncomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_provision_income_account_id');
    }

    public function penaltyReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_penalty_receivable_account_id');
    }

    public function rateHistory(): HasMany
    {
        return $this->hasMany(LoanProductRateHistory::class)->orderByDesc('effective_from');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** Non-retroactive rate resolution — see SavingsProduct::rateAt() for the same pattern. */
    public function rateAt(?Carbon $date = null): ?LoanProductRateHistory
    {
        $date ??= now();

        return $this->rateHistory()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Jumlah approval berbeda orang yang dibutuhkan untuk plafon tertentu
     * (PRD §7.3 "skema approval berjenjang" — AUTH-12).
     */
    public function requiredApprovalCountFor(float $principal): int
    {
        if ($this->approval_threshold !== null && $principal > (float) $this->approval_threshold) {
            return 2;
        }

        return 1;
    }

    public function isReadyForActivation(): bool
    {
        return $this->coa_receivable_account_id !== null
            && $this->coa_interest_income_account_id !== null
            && $this->coa_provision_income_account_id !== null
            && $this->coa_penalty_receivable_account_id !== null;
    }
}
