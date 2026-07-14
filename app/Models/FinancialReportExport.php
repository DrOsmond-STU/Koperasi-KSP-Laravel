<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReportExport extends Model
{
    protected $fillable = [
        'report_kind',
        'basis',
        'branch_id',
        'period_start',
        'period_end',
        'as_of_date',
        'format',
        'status',
        'file_path',
        'error_message',
        'requested_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'as_of_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isReady(): bool
    {
        return $this->status === 'selesai';
    }
}
