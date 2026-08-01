<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generated once at disbursement by LoanScheduleCalculator — never
 * regenerated in place; restructuring/rescheduling creates new rows
 * referencing the change, per PRD §8 ("bukan overwrite jadwal asli").
 */
class LoanSchedule extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'paid_amount',
        'paid_principal_amount',
        'paid_interest_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_principal_amount' => 'decimal:2',
            'paid_interest_amount' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
