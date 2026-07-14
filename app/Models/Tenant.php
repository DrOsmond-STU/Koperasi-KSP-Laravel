<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'code',
        'owner_name',
        'location',
        'status',
        'member_id',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function billings(): HasMany
    {
        return $this->hasMany(FeeBilling::class);
    }
}
