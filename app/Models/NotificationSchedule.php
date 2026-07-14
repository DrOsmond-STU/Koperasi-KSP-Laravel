<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'trigger_type',
        'day_offset',
        'send_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_offset' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
