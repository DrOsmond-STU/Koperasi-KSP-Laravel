<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SavingsProduct extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'interest_method',
        'minimum_initial_deposit',
        'minimum_subsequent_deposit',
        'admin_fee',
        'admin_fee_period',
        'withdrawal_penalty_percentage',
        'coa_liability_account_id',
        'coa_interest_expense_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_initial_deposit' => 'decimal:2',
            'minimum_subsequent_deposit' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'withdrawal_penalty_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_liability_account_id');
    }

    public function interestExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_interest_expense_account_id');
    }

    public function rateHistory(): HasMany
    {
        return $this->hasMany(SavingsProductRateHistory::class)->orderByDesc('effective_from');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class);
    }

    /**
     * Tarif yang berlaku pada tanggal tertentu (default: hari ini) — memilih
     * baris histori dengan effective_from terbaru yang <= tanggal tsb, tidak
     * pernah mengambil baris dengan effective_from di masa depan (PRD §2.4:
     * histori tarif tidak retroaktif).
     */
    public function rateAt(?Carbon $date = null): ?SavingsProductRateHistory
    {
        $date ??= now();

        return $this->rateHistory()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }

    public function isReadyForActivation(): bool
    {
        return $this->coa_liability_account_id !== null && $this->coa_interest_expense_account_id !== null;
    }
}
