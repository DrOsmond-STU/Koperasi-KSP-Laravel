<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = [
        'report_template_id',
        'report_type',
        'columns',
        'filters',
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
            'columns' => 'array',
            'filters' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class);
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
