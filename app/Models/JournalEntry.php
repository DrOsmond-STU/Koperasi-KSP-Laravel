<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only. Do not add update/delete logic here — corrections are made by
 * posting a new entry via JournalEngine::reverse() (PRD §2.3, §2.7).
 */
class JournalEntry extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'created_by',
        'reversal_of_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_entry_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
