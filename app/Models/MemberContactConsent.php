<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberContactConsent extends Model
{
    use Auditable;

    protected $fillable = [
        'member_id',
        'channel',
        'consented',
        'consented_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'consented' => 'boolean',
            'consented_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function grant(): void
    {
        $this->update([
            'consented' => true,
            'consented_at' => now(),
            'withdrawn_at' => null,
        ]);
    }

    public function withdraw(): void
    {
        $this->update([
            'consented' => false,
            'withdrawn_at' => now(),
        ]);
    }
}
