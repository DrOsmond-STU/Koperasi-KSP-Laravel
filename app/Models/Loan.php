<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\AuthorizesOwner;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Loan extends Model
{
    use Auditable, AuthorizesOwner, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'member_id',
        'loan_product_id',
        'loan_number',
        'principal_amount',
        'tenor_days',
        'tenor_unit',
        'interest_rate_percentage',
        'provision_fee_amount',
        'required_approval_count',
        'status',
        'collectibility',
        'created_by',
        'submitted_at',
        'disbursed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'reversal_journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_rate_percentage' => 'decimal:3',
            'provision_fee_amount' => 'decimal:2',
            'submitted_at' => 'date',
            'disbursed_at' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Satuan tenor di-snapshot dari produk saat pengajuan (sejalan dengan
     * interest_rate_percentage) — kalau produknya kelak diubah satuannya,
     * pinjaman yang terlanjur berjalan tetap terbaca dengan satuan saat ia
     * dibuat. Lihat LoanProduct::usesDailyTenor().
     */
    public function usesDailyTenor(): bool
    {
        return $this->tenor_unit === 'hari';
    }

    /** Label tenor untuk tampilan/cetak — "100 hari" (pinjaman harian) atau "12 bulan" (pinjaman bulanan). */
    public function tenorLabel(): string
    {
        $unit = $this->usesDailyTenor() ? 'hari' : 'bulan';

        return "{$this->tenor_days} {$unit}";
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LoanApproval::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_number');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /**
     * The disbursement journal entry, found via JournalEntry's polymorphic
     * `source` (loans have no journal_entry_id column — disbursement is the
     * ONLY journal a loan itself is the source of; POS "hutang" loans are
     * instead a line inside the POS sale's own combined journal entry).
     */
    public function disbursementJournalEntry(): MorphOne
    {
        return $this->morphOne(JournalEntry::class, 'source');
    }

    public function approvalCount(): int
    {
        return $this->approvals()->where('decision', 'setuju')->count();
    }

    public function isFullyApproved(): bool
    {
        return $this->approvalCount() >= $this->required_approval_count;
    }

    public function hasBeenDecidedBy(int $userId): bool
    {
        return $this->approvals()->where('approved_by', $userId)->exists();
    }
}
