<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class ReportTemplate extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'report_type',
        'columns',
        'filters',
        'created_by',
        'shared_role_id',
    ];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'filters' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sharedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'shared_role_id');
    }
}
